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

from fastapi import FastAPI, HTTPException
from fastapi.responses import Response
from pydantic import BaseModel, Field
from ukrainian_tts.tts import TTS, Voices, Stress

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Global variables
tts_model: Optional[TTS] = None
synthesis_lock = asyncio.Lock()


class SynthesizeRequest(BaseModel):
    """Request model for text synthesis."""
    text: str = Field(..., min_length=1, max_length=500, description="Text to synthesize")
    speaker: str = Field(default="lada", description="Speaker name")


class HealthResponse(BaseModel):
    """Response model for health check."""
    status: str
    model_loaded: bool


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load TTS model on startup, cleanup on shutdown."""
    global tts_model
    
    logger.info("Loading Ukrainian TTS model...")
    try:
        # Load the model
        tts_model = TTS(device='cpu')
        logger.info("Ukrainian TTS model loaded successfully")
    except Exception as e:
        logger.error(f"Failed to load TTS model: {e}")
        raise
    
    yield
    
    logger.info("Shutting down TTS service")
    tts_model = None


# Create FastAPI app
app = FastAPI(
    title="Ukrainian TTS Service",
    description="Text-to-speech synthesis for Ukrainian language",
    version="1.0.0",
    lifespan=lifespan
)


@app.get("/health", response_model=HealthResponse)
async def health_check():
    """
    Health check endpoint.
    
    Returns healthy status only when the model is loaded and ready.
    """
    return HealthResponse(
        status="healthy" if tts_model is not None else "unhealthy",
        model_loaded=tts_model is not None
    )


@app.post("/synthesize")
async def synthesize_speech(request: SynthesizeRequest):
    """
    Synthesize speech from text.
    
    Uses asyncio.Lock to ensure only one request is processed at a time,
    preventing memory issues from concurrent model usage.
    
    Returns:
        WAV audio file
    """
    if tts_model is None:
        raise HTTPException(status_code=503, detail="TTS model not loaded")
    
    logger.info(f"Synthesis request: text_length={len(request.text)}, speaker={request.speaker}")
    
    # Acquire lock to process one request at a time
    async with synthesis_lock:
        try:
            # Run synthesis in thread pool to avoid blocking event loop
            loop = asyncio.get_event_loop()
            wav_data = await loop.run_in_executor(
                None,
                _synthesize_sync,
                request.text,
                request.speaker
            )
            
            logger.info(f"Synthesis completed: audio_size={len(wav_data)} bytes")
            
            return Response(
                content=wav_data,
                media_type="audio/wav",
                headers={
                    "Content-Disposition": "attachment; filename=speech.wav"
                }
            )
            
        except Exception as e:
            logger.error(f"Synthesis failed: {e}")
            raise HTTPException(status_code=500, detail=f"Synthesis failed: {str(e)}")


def _synthesize_sync(text: str, speaker: str) -> bytes:
    """
    Synchronous synthesis function to run in thread pool.
    
    Args:
        text: Text to synthesize
        speaker: Speaker name
        
    Returns:  
        WAV audio data as bytes
    """
    import tempfile
    import os
    
    # Map speaker names to Voices enum
    voice_map = {
        'lada': Voices.Lada.value,
        'mykyta': Voices.Mykyta.value,
        'tetiana': Voices.Tetiana.value,
        'dmytro': Voices.Dmytro.value,
        'oleksa': Voices.Oleksa.value,
    }
    
    voice = voice_map.get(speaker.lower(), Voices.Lada.value)
    
    # Create temporary file for WAV output
    with tempfile.NamedTemporaryFile(suffix='.wav', delete=False) as temp_file:
        temp_path = temp_file.name
    
    try:
        # Generate speech - tts() writes to file object
        with open(temp_path, 'wb') as f:
            _, output_text = tts_model.tts(text, voice, Stress.Dictionary.value, f)
        
        logger.info(f"Generated speech with stressed text: {output_text}")
        
        # Read WAV data
        with open(temp_path, 'rb') as f:
            wav_data = f.read()
        
        return wav_data
    finally:
        # Clean up temporary file
        if os.path.exists(temp_path):
            os.unlink(temp_path)


if __name__ == "__main__":
    import uvicorn
    port = int(os.environ.get("TTS_PORT", 5001))
    uvicorn.run(app, host="0.0.0.0", port=port, workers=1)
