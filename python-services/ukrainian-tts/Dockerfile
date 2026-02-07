# ============================================
# Stage 1: Builder - Compile dependencies
# ============================================
FROM python:3.9-slim AS builder

WORKDIR /app

# Install build tools (build time only)
RUN apt-get update && apt-get install -y \
    git \
    build-essential \
    gcc \
    g++ \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements
COPY requirements.txt .

# Install PyTorch CPU-only version
RUN pip install --no-cache-dir torch==2.1.0 torchaudio==2.1.0 --index-url https://download.pytorch.org/whl/cpu

# Install all other dependencies (including git-based)
RUN pip install --no-cache-dir -r requirements.txt

# Cleanup unnecessary files to reduce image size
RUN find /usr/local/lib/python3.9/site-packages -type d -name "tests" -exec rm -rf {} + 2>/dev/null || true && \
    find /usr/local/lib/python3.9/site-packages -type d -name "test" -exec rm -rf {} + 2>/dev/null || true && \
    find /usr/local/lib/python3.9/site-packages -type d -name "__pycache__" -exec rm -rf {} + 2>/dev/null || true && \
    find /usr/local/lib/python3.9/site-packages -name "*.pyc" -delete && \
    find /usr/local/lib/python3.9/site-packages -name "*.pyo" -delete && \
    find /usr/local/lib/python3.9/site-packages -type d -name "*.dist-info" -exec sh -c 'rm -rf "$1"/RECORD "$1"/INSTALLER' _ {} \; 2>/dev/null || true

# ============================================
# Stage 2: Runtime - Minimal production image
# ============================================
FROM python:3.9-slim AS runtime

WORKDIR /app

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Copy installed Python packages from builder stage
# Use site-packages to avoid git dependency in runtime
COPY --from=builder /usr/local/lib/python3.9/site-packages /usr/local/lib/python3.9/site-packages
COPY --from=builder /usr/local/bin /usr/local/bin

# Copy application code
COPY main.py .

# Environment settings
ENV PYTHONUNBUFFERED=1 \
    OMP_NUM_THREADS=2 \
    MKL_NUM_THREADS=2 \
    TTS_PORT=5001

# Expose port
EXPOSE ${TTS_PORT}

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD python -c "import urllib.request, os; urllib.request.urlopen(f'http://localhost:{os.environ[\"TTS_PORT\"]}/health')" || exit 1

# Run application
CMD ["sh", "-c", "uvicorn main:app --host 0.0.0.0 --port ${TTS_PORT} --workers 1"]
