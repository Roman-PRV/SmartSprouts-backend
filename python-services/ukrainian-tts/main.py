"""
Ukrainian TTS Microservice

FastAPI service for text-to-speech synthesis using robinhad/ukrainian-tts.
Implements single-request processing with asyncio.Lock to prevent memory issues.
"""

import asyncio
import io
import logging
import os
import re
from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException, Request, status
from fastapi.responses import Response, JSONResponse
from pydantic import BaseModel, Field
from pydub import AudioSegment
from ukrainian_tts.tts import TTS, Voices, Stress

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)


def get_env_int(name: str, default: int, min_value: int = 1) -> int:
    """
    Safely get an integer from environment variables with validation.
    """
    val_str = os.environ.get(name)
    if val_str is None:
        return default

    try:
        val = int(val_str)
        if val < min_value:
            logger.warning(f"Environment variable {name}={val} is less than {min_value}. Using default: {default}")
            return default
        return val
    except ValueError:
        logger.warning(f"Environment variable {name}='{val_str}' is not a valid integer. Using default: {default}")
        return default


MAX_TEXT_LENGTH = get_env_int("TTS_MAX_TEXT_LENGTH", 2000)
CHUNK_SIZE = get_env_int("TTS_CHUNK_SIZE", 200)


class SynthesizeRequest(BaseModel):
    """Request model for text synthesis."""
    text: str = Field(..., min_length=1, description="Text to synthesize")
    speaker: str = Field(default="lada", description="Speaker name")


class HealthResponse(BaseModel):
    """Response model for health check."""
    status: str
    model_loaded: bool


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load TTS model on startup, cleanup on shutdown."""
    logger.info("Loading Ukrainian TTS model...")
    try:
        # Get cache directory from environment variable or default to '.'
        cache_dir = os.environ.get("TTS_CACHE_DIR", ".")
        if cache_dir != "." and not os.path.exists(cache_dir):
            logger.info(f"Creating cache directory: {cache_dir}")
            os.makedirs(cache_dir, exist_ok=True)

        # espnet2 (used by ukrainian-tts) expects feats_stats.npz to be in the CWD
        # as specified in the model's config.yaml. We change directory temporarily.
        original_cwd = os.getcwd()
        os.chdir(cache_dir)

        try:
            # Store model and lock in app.state for better lifecycle management
            # We use '.' because we already changed directory to cache_dir
            app.state.tts_model = TTS(cache_folder='.', device='cpu')
            app.state.synthesis_lock = asyncio.Lock()
            logger.info(f"Ukrainian TTS model loaded successfully in {cache_dir}")
        finally:
            # Change back to original directory
            os.chdir(original_cwd)

    except Exception as e:
        logger.error(f"Failed to load TTS model: {e}")
        raise

    yield

    logger.info("Shutting down TTS service")
    app.state.tts_model = None


app = FastAPI(
    title="Ukrainian TTS Service",
    description="Text-to-speech synthesis for Ukrainian language",
    version="1.0.0",
    lifespan=lifespan
)


@app.get("/health", response_model=HealthResponse)
async def health_check(request: Request):
    """
    Health check endpoint.

    Returns healthy status (200) only when the model is loaded and ready.
    Otherwise returns unhealthy status (503).
    """
    tts_model = getattr(request.app.state, 'tts_model', None)
    is_healthy = tts_model is not None

    return JSONResponse(
        status_code=200 if is_healthy else 503,
        content={
            "status": "healthy" if is_healthy else "unhealthy",
            "model_loaded": is_healthy
        }
    )


@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    """
    Global exception handler to prevent leaking internal details.
    """
    logger.exception(f"Unhandled exception: {exc}")
    return JSONResponse(
        status_code=500,
        content={"detail": "An internal server error occurred. Please try again later."}
    )


@app.post("/synthesize")
async def synthesize_speech(request: Request, body: SynthesizeRequest):
    """
    Synthesize speech from text.

    Uses asyncio.Lock to ensure only one request is processed at a time,
    preventing memory issues from concurrent model usage.

    Returns:
        MP3 audio file
    """
    tts_model = getattr(request.app.state, 'tts_model', None)
    synthesis_lock = getattr(request.app.state, 'synthesis_lock', None)

    if tts_model is None or synthesis_lock is None:
        raise HTTPException(status_code=503, detail="TTS model or lock not initialized")

    logger.info(f"Synthesis request: text_length={len(body.text)}, speaker={body.speaker}")

    # Fail fast: validate length before acquiring the lock
    if len(body.text) > MAX_TEXT_LENGTH:
        logger.warning(f"Text too long: {len(body.text)} > {MAX_TEXT_LENGTH}")
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Text is too long. Maximum allowed length is {MAX_TEXT_LENGTH} characters."
        )

    # Acquire lock to process one request at a time
    async with synthesis_lock:
        try:
            loop = asyncio.get_running_loop()
            mp3_data = await loop.run_in_executor(
                None,
                _synthesize_sync,
                tts_model,
                body.text,
                body.speaker
            )

            logger.info(f"Synthesis completed: audio_size={len(mp3_data)} bytes")

            return Response(
                content=mp3_data,
                media_type="audio/mpeg",
                headers={
                    "Content-Disposition": "attachment; filename=speech.mp3"
                }
            )

        except MemoryError:
            logger.exception("OOM during synthesis")
            raise HTTPException(
                # Fix #3: 507 is WebDAV disk-storage status; 503 is correct for OOM
                status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
                detail="Server is temporarily unavailable due to high load"
            )
        except HTTPException:
            raise
        except Exception:
            logger.exception("Synthesis failed")
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail="Synthesis failed. Please try again later."
            )


def _synthesize_sync(tts_model: TTS, text: str, speaker: str) -> bytes:
    """
    Synchronous synthesis function to run in thread pool.

    Args:
        tts_model: The TTS model instance
        text: Text to synthesize
        speaker: Speaker name

    Returns:
        MP3 audio data as bytes
    """
    # Dynamic speaker mapping: search for speaker in Voices enum
    voice = Voices.Tetiana.value  # Default
    speaker_clean = speaker.strip().lower()

    for v in Voices:
        if v.name.lower() == speaker_clean:
            voice = v.value
            break

    if len(text) <= CHUNK_SIZE:
        return _process_chunk_to_mp3(tts_model, text, voice)

    # Split text into chunks
    final_chunks = _split_text_into_chunks(text, CHUNK_SIZE)

    logger.info(f"Synthesizing in {len(final_chunks)} chunks")

    audio_segments = []
    for chunk in final_chunks:
        if not chunk.strip():
            continue
        segment = _process_chunk_to_segment(tts_model, chunk, voice)
        audio_segments.append(segment)

    if not audio_segments:
        raise ValueError("No audio segments generated")

    # Combine segments
    combined_audio = audio_segments[0]
    for next_segment in audio_segments[1:]:
        combined_audio += next_segment

    with io.BytesIO() as mp3_buffer:
        combined_audio.export(mp3_buffer, format="mp3", bitrate="128k")
        return mp3_buffer.getvalue()


def _split_text_into_chunks(text: str, max_chunk_size: int) -> list[str]:
    """Split text into manageable chunks by punctuation and length."""
    # Split by '.', '!', '?', or newline, keeping the delimiter
    chunks_raw = re.split(r'([.!?\n]+)', text)

    processed_chunks = []
    current_chunk = ""

    for i in range(0, len(chunks_raw), 2):
        sentence = chunks_raw[i]
        delimiter = chunks_raw[i+1] if i+1 < len(chunks_raw) else ""
        combined = sentence + delimiter

        if len(current_chunk) + len(combined) > max_chunk_size and current_chunk:
            processed_chunks.append(current_chunk.strip())
            current_chunk = combined
        else:
            current_chunk += combined

    if current_chunk:
        processed_chunks.append(current_chunk.strip())

    # If a single chunk is still too long (no punctuation), force split it
    final_chunks = []
    for chunk in processed_chunks:
        if len(chunk) > max_chunk_size:
            for i in range(0, len(chunk), max_chunk_size):
                final_chunks.append(chunk[i:i+max_chunk_size])
        else:
            final_chunks.append(chunk)

    return final_chunks


def _process_chunk_to_segment(tts_model: TTS, text: str, voice: str) -> AudioSegment:
    """Helper to process a single chunk and return AudioSegment."""
    with io.BytesIO() as buffer:
        try:
            # Generate speech
            tts_model.tts(text, voice, Stress.Dictionary.value, buffer)
            buffer.seek(0)
            return AudioSegment.from_wav(buffer)
        except Exception as e:
            logger.error(f"Error in chunk synthesis: {e}")
            raise


def _process_chunk_to_mp3(tts_model: TTS, text: str, voice: str) -> bytes:
    """Helper to process a single chunk and return MP3 bytes."""
    audio = _process_chunk_to_segment(tts_model, text, voice)
    with io.BytesIO() as mp3_buffer:
        audio.export(mp3_buffer, format="mp3", bitrate="128k")
        return mp3_buffer.getvalue()


if __name__ == "__main__":
    import uvicorn
    port = get_env_int("TTS_PORT", 5001)
    uvicorn.run(app, host="0.0.0.0", port=port, workers=1)