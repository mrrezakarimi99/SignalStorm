"""
FastAPI Application for ML Trainer Service

Exposes REST API endpoints for model training and prediction
"""

from fastapi import FastAPI, HTTPException
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
        return {
            'success': True,
            'models': models,
            'count': len(models)
        }
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


# Run with: uvicorn src.api.main:app --host 0.0.0.0 --port 8001 --reload

