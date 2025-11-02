"""
FastAPI Application for ML Trainer Service

Exposes REST API endpoints for model training and prediction
"""

from fastapi import FastAPI, HTTPException
from fastapi.responses import FileResponse
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
import os

from ..trainers.lstm_trainer import LSTMTrainer
from ..storage.disk_storage import DiskStorage
from ..contracts.trainer import ITrainer


# Pydantic models for request/response
class TrainRequest(BaseModel):
    data: List[Dict[str, Any]]
    config: Dict[str, Any] = {}


class PredictRequest(BaseModel):
    features: List[Dict[str, Any]]
    model_version: Optional[str] = None


class HealthResponse(BaseModel):
    status: str
    service: str
    version: str


# Initialize FastAPI app
app = FastAPI(
    title="SignalStorm ML Trainer",
    description="ML model training and prediction service",
    version="1.0.0"
)

# Initialize storage and trainer (Dependency Injection)
storage_type = os.getenv('MODEL_STORAGE', 'disk')
model_path = os.getenv('MODEL_PATH', '/models')

if storage_type == 'disk':
    storage = DiskStorage(base_path=model_path)
else:
    # Could add MinIO storage here
    storage = DiskStorage(base_path=model_path)

# Create trainer instance
trainer: ITrainer = LSTMTrainer(storage=storage)


@app.get("/", response_model=HealthResponse)
async def root():
    """Root endpoint"""
    return {
        "status": "running",
        "service": "SignalStorm ML Trainer",
        "version": "1.0.0"
    }


@app.get("/health", response_model=HealthResponse)
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "service": "ML Trainer",
        "version": "1.0.0"
    }


@app.post("/train")
async def train_model(request: TrainRequest):
    """
    Train a new model

    Args:
        request: Training data and configuration

    Returns:
        Training results with model version and metrics
    """
    try:
        result = trainer.train(request.data, request.config)

        if not result.get('success', False):
            raise HTTPException(
                status_code=400,
                detail=result.get('error', 'Training failed')
            )

        return result

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/predict")
async def predict(request: PredictRequest):
    """
    Make predictions using trained model

    Args:
        request: Features and optional model version

    Returns:
        Predictions
    """
    try:
        result = trainer.predict(request.features, request.model_version)

        if not result.get('success', False):
            raise HTTPException(
                status_code=400,
                detail=result.get('error', 'Prediction failed')
            )

        return result

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/model/info")
async def get_model_info(model_version: Optional[str] = None):
    """
    Get model information

    Args:
        model_version: Optional specific model version

    Returns:
        Model metadata
    """
    try:
        result = trainer.get_model_info(model_version)

        if not result.get('success', False):
            raise HTTPException(
                status_code=404,
                detail=result.get('error', 'Model not found')
            )

        return result

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/models")
async def list_models():
    """
    List all available models

    Returns:
        List of model versions
    """
    try:
        models = storage.list_models()

        # Get detailed info for each model
        model_details = []
        for model_version in models:
            model_path = storage._get_model_path(model_version)
            if os.path.exists(model_path):
                file_size = os.path.getsize(model_path)
                file_mtime = os.path.getmtime(model_path)

                # Parse model version to extract info
                parts = model_version.split('_')
                symbol = parts[1] if len(parts) > 1 else 'unknown'
                interval = parts[2] if len(parts) > 2 else 'unknown'
                timestamp = parts[3] if len(parts) > 3 else 'unknown'

                model_details.append({
                    'model_version': model_version,
                    'symbol': symbol,
                    'interval': interval,
                    'timestamp': timestamp,
                    'file_size': file_size,
                    'file_size_mb': round(file_size / (1024 * 1024), 2),
                    'modified_at': file_mtime
                })

        return {
            'success': True,
            'models': models,
            'details': model_details,
            'count': len(models)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/models/{model_version}/download")
async def download_model(model_version: str):
    """
    Download a specific model file

    Args:
        model_version: Model version to download

    Returns:
        Model file for download
    """
    try:
        if not storage.exists(model_version):
            raise HTTPException(status_code=404, detail=f"Model '{model_version}' not found")

        model_path = storage._get_model_path(model_version)

        return FileResponse(
            path=model_path,
            media_type='application/octet-stream',
            filename=f"{model_version}.h5"
        )
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@app.get("/models/{model_version}/info")
async def get_specific_model_info(model_version: str):
    """
    Get detailed information about a specific model

    Args:
        model_version: Model version to get info for

    Returns:
        Detailed model information
    """
    try:
        if not storage.exists(model_version):
            raise HTTPException(status_code=404, detail=f"Model '{model_version}' not found")

        model_path = storage._get_model_path(model_version)
        file_size = os.path.getsize(model_path)
        file_mtime = os.path.getmtime(model_path)

        # Parse model version
        parts = model_version.split('_')

        return {
            'success': True,
            'model_version': model_version,
            'architecture': parts[0] if len(parts) > 0 else 'unknown',
            'symbol': parts[1] if len(parts) > 1 else 'unknown',
            'interval': parts[2] if len(parts) > 2 else 'unknown',
            'timestamp': parts[3] if len(parts) > 3 else 'unknown',
            'file_path': model_path,
            'file_size': file_size,
            'file_size_mb': round(file_size / (1024 * 1024), 2),
            'modified_at': file_mtime,
            'exists': True
        }
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# Run with: uvicorn src.api.main:app --host 0.0.0.0 --port 8001 --reload

