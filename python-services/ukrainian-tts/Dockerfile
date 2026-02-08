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

# Create virtual environment
RUN python -m venv /opt/venv
# Ensure we use the virtualenv
ENV PATH="/opt/venv/bin:$PATH"

# Copy requirements
COPY requirements.txt .

# Install PyTorch CPU-only version
RUN pip install --no-cache-dir torch==2.1.0 torchaudio==2.1.0 --index-url https://download.pytorch.org/whl/cpu

# Install all other dependencies (including git-based)
RUN pip install --no-cache-dir -r requirements.txt

# Cleanup unnecessary files to reduce image size
RUN find /opt/venv/lib/python3.9/site-packages -type d -name "tests" -exec rm -rf {} + 2>/dev/null || true && \
    find /opt/venv/lib/python3.9/site-packages -type d -name "test" -exec rm -rf {} + 2>/dev/null || true && \
    find /opt/venv/lib/python3.9/site-packages -type d -name "__pycache__" -exec rm -rf {} + 2>/dev/null || true && \
    find /opt/venv/lib/python3.9/site-packages -name "*.pyc" -delete && \
    find /opt/venv/lib/python3.9/site-packages -name "*.pyo" -delete && \
    find /opt/venv/lib/python3.9/site-packages -type d -name "*.dist-info" -exec sh -c 'rm -rf "$1"/RECORD "$1"/INSTALLER' _ {} \; 2>/dev/null || true

# ============================================
# Stage 2: Runtime - Minimal production image
# ============================================
FROM python:3.9-slim AS runtime

# Create a non-root user and group
RUN groupadd -g 1000 appgroup && \
    useradd -u 1000 -g appgroup -s /bin/sh -m appuser

WORKDIR /app

# Install runtime dependencies
RUN apt-get update && apt-get install -y \
    ffmpeg \
    && rm -rf /var/lib/apt/lists/*

# Copy virtual environment from builder stage
COPY --from=builder /opt/venv /opt/venv

# Set environment variables
# TORCH_HOME is set to /app/.cache so appuser can write models there
ENV PATH="/opt/venv/bin:$PATH" \
    PYTHONUNBUFFERED=1 \
    OMP_NUM_THREADS=2 \
    MKL_NUM_THREADS=2 \
    TTS_PORT=5001 \
    TORCH_HOME=/app/.cache

# Copy application code
COPY main.py .

# Ensure appuser owns the application directory and virtual environment
RUN chown -R appuser:appgroup /app /opt/venv

# Switch to non-root user
USER appuser

# Expose port
EXPOSE ${TTS_PORT}

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD python -c "import urllib.request, os; urllib.request.urlopen('http://localhost:' + os.environ.get('TTS_PORT', '5001') + '/health')" || exit 1

# Run application
CMD ["sh", "-c", "uvicorn main:app --host 0.0.0.0 --port ${TTS_PORT} --workers 1"]

