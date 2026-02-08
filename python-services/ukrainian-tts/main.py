"""
Ukrainian TTS Microservice

FastAPI service for text-to-speech synthesis using robinhad/ukrainian-tts.
Implements single-request processing with asyncio.Lock to prevent memory issues.
"""

import asyncio
import io
import logging
import os
from contextlib import asynccontextmanager
from typing import Optional

from fastapi import FastAPI, HTTPException, Request
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

class SynthesizeRequest(BaseModel):
    """Request model for text synthesis."""
    text: str = Field(..., min_length=1, max_length=500, description="Text to synthesize")
    speaker: str = Field(default="lada", description="Speaker name")


class HealthResponse(BaseModel):
    """Response model for health check."""
    status: str
    model_loaded: bool


# Create FastAPI app
app = FastAPI(
    title="Ukrainian TTS Service",
    description="Text-to-speech synthesis for Ukrainian language",
    version="1.0.0"
)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load TTS model on startup, cleanup on shutdown."""
    logger.info("Loading Ukrainian TTS model...")
    try:
        # Store model and lock in app.state for better lifecycle management
        app.state.tts_model = TTS(device='cpu')
        app.state.synthesis_lock = asyncio.Lock()
        logger.info("Ukrainian TTS model loaded successfully")
    except Exception as e:
        logger.error(f"Failed to load TTS model: {e}")
        raise
    
    yield
    
    logger.info("Shutting down TTS service")
    app.state.tts_model = None


app.router.lifespan_context = lifespan


@app.get("/health", response_model=HealthResponse)
async def health_check(request: Request):
    """
    Health check endpoint.
    
    Returns healthy status only when the model is loaded and ready.
    """
    tts_model = getattr(request.app.state, 'tts_model', None)
    return HealthResponse(
        status="healthy" if tts_model is not None else "unhealthy",
        model_loaded=tts_model is not None
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
    
    # Acquire lock to process one request at a time
    async with synthesis_lock:
        try:
            # Run synthesis in thread pool to avoid blocking event loop
            loop = asyncio.get_event_loop()
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
            raise HTTPException(status_code=507, detail="Server is temporary unavailable due to high load")
        except Exception:
            logger.exception("Synthesis failed")
            raise HTTPException(status_code=500, detail="Synthesis failed. Please try again later.")


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
    voice = Voices.Lada.value  # Default
    speaker_clean = speaker.strip().lower()
    
    for v in Voices:
        if v.name.lower() == speaker_clean:
            voice = v.value
            break
    
    # Use io.BytesIO for in-memory WAV generation via context manager
    with io.BytesIO() as buffer:
        try:
            # Generate speech - tts() writes to file-like object
            _, output_text = tts_model.tts(text, voice, Stress.Dictionary.value, buffer)
            
            # Use debug level for potentially sensitive text
            logger.debug(f"Generated speech with stressed text: {output_text}")
            logger.info(f"Generated speech: text_length={len(text)} characters")
            
            # Get bytes from buffer
            buffer.seek(0)
            
            # Convert WAV to MP3 using pydub
            logger.info("Converting WAV to MP3...")
            audio = AudioSegment.from_wav(buffer)
            
            with io.BytesIO() as mp3_buffer:
                audio.export(mp3_buffer, format="mp3", bitrate="128k")
                return mp3_buffer.getvalue()
                
        except Exception as e:
            logger.error(f"Error in synchronous synthesis: {e}")
            raise


if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("TTS_PORT", 5001))
    uvicorn.run(app, host="0.0.0.0", port=port, workers=1)
