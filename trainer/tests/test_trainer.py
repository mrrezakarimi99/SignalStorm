"""
Tests for LSTM Trainer

Tests model training and prediction functionality
"""

import pytest
import numpy as np
from src.trainers.lstm_trainer import LSTMTrainer, TENSORFLOW_AVAILABLE
from src.storage.disk_storage import DiskStorage
import tempfile
import os


@pytest.fixture
def storage():
    """Create temporary storage for tests"""
    with tempfile.TemporaryDirectory() as tmpdir:
        yield DiskStorage(base_path=tmpdir)


@pytest.fixture
def trainer(storage):
    """Create trainer instance for tests"""
    if not TENSORFLOW_AVAILABLE:
        pytest.skip("TensorFlow not available")
    return LSTMTrainer(storage=storage)


@pytest.fixture
def mock_data():
    """Generate mock training data"""
    data = []
    for i in range(100):
        data.append({
            'open_norm': 0.5 + (i * 0.001),
            'high_norm': 0.6 + (i * 0.001),
            'low_norm': 0.4 + (i * 0.001),
            'close_norm': 0.55 + (i * 0.001),
            'volume_norm': 0.5,
        })
    return data


def test_trainer_initialization(trainer):
    """Test trainer can be initialized"""
    assert trainer is not None
    assert trainer.model is None
    assert trainer.current_version is None


def test_train_with_insufficient_data(trainer):
    """Test training with insufficient data"""
    result = trainer.train([], {'epochs': 1})
    assert result['success'] is False
    assert 'error' in result


def test_train_model(trainer, mock_data):
    """Test model training"""
    config = {
        'epochs': 2,
        'batch_size': 16,
        'symbol': 'BTCUSDT',
        'interval': '1h',
    }

    result = trainer.train(mock_data, config)

    assert result['success'] is True
    assert 'model_version' in result
    assert 'metrics' in result
    assert result['metrics']['train_loss'] > 0


def test_save_and_load_model(trainer, mock_data):
    """Test saving and loading model"""
    config = {
        'epochs': 1,
        'symbol': 'BTCUSDT',
        'interval': '1h',
    }

    # Train model
    result = trainer.train(mock_data, config)
    assert result['success'] is True

    model_version = result['model_version']

    # Check model exists
    assert trainer.storage.exists(model_version)

    # Create new trainer and load model
    trainer2 = LSTMTrainer(storage=trainer.storage)
    loaded = trainer2.load_model(model_version)
    assert loaded is True
    assert trainer2.current_version == model_version


def test_prediction(trainer, mock_data):
    """Test making predictions"""
    config = {
        'epochs': 1,
        'symbol': 'BTCUSDT',
        'interval': '1h',
    }

    # Train model
    trainer.train(mock_data, config)

    # Make prediction
    features = mock_data[-10:]  # Use last 10 candles
    result = trainer.predict(features)

    assert result['success'] is True
    assert 'predictions' in result
    assert len(result['predictions']) > 0


def test_get_model_info(trainer, mock_data):
    """Test getting model info"""
    config = {
        'epochs': 1,
        'symbol': 'BTCUSDT',
        'interval': '1h',
    }

    # Train model
    train_result = trainer.train(mock_data, config)
    model_version = train_result['model_version']

    # Get info
    info = trainer.get_model_info(model_version)

    assert info['success'] is True
    assert info['model_version'] == model_version
    assert info['exists'] is True
    assert info['architecture'] == 'LSTM'

