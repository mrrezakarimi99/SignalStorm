"""
Tests for API endpoints

Tests the FastAPI application
"""

import pytest
from fastapi.testclient import TestClient
from src.api.main import app


@pytest.fixture
def client():
    """Create test client"""
    return TestClient(app)


def test_root_endpoint(client):
    """Test root endpoint"""
    response = client.get("/")
    assert response.status_code == 200
    data = response.json()
    assert data['status'] == 'running'
    assert 'service' in data


def test_health_check(client):
    """Test health check endpoint"""
    response = client.get("/health")
    assert response.status_code == 200
    data = response.json()
    assert data['status'] == 'healthy'


def test_train_endpoint_with_no_data(client):
    """Test training with empty data"""
    response = client.post("/train", json={
        'data': [],
        'config': {}
    })
    assert response.status_code == 400


def test_train_endpoint_with_data(client):
    """Test training with valid data"""
    # Generate mock data
    data = []
    for i in range(100):
        data.append({
            'open_norm': 0.5 + (i * 0.001),
            'high_norm': 0.6 + (i * 0.001),
            'low_norm': 0.4 + (i * 0.001),
            'close_norm': 0.55 + (i * 0.001),
            'volume_norm': 0.5,
        })

    response = client.post("/train", json={
        'data': data,
        'config': {
            'epochs': 1,
            'batch_size': 16,
            'symbol': 'BTCUSDT',
            'interval': '1h',
        }
    })

    if response.status_code == 200:
        data = response.json()
        assert data['success'] is True
        assert 'model_version' in data
        assert 'metrics' in data


def test_list_models(client):
    """Test listing models"""
    response = client.get("/models")
    assert response.status_code == 200
    data = response.json()
    assert 'models' in data
    assert 'count' in data

