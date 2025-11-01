"""
LSTM Trainer Implementation

Implements ML training using LSTM neural network
Single Responsibility: Only handles LSTM model training
"""

import numpy as np
from datetime import datetime
from typing import Dict, List, Any, Optional
import os

# TensorFlow/Keras imports
try:
    from tensorflow import keras
    from tensorflow.keras.models import Sequential, load_model
    from tensorflow.keras.layers import LSTM, Dense, Dropout
    from tensorflow.keras.callbacks import EarlyStopping
    TENSORFLOW_AVAILABLE = True
except ImportError:
    TENSORFLOW_AVAILABLE = False

from ..contracts.trainer import ITrainer
from ..contracts.storage import IModelStorage


class LSTMTrainer(ITrainer):
    """
    LSTM-based trainer for price prediction

    Implements time series forecasting using LSTM neural networks
    """

    def __init__(self, storage: IModelStorage):
        if not TENSORFLOW_AVAILABLE:
            raise ImportError("TensorFlow is required for LSTMTrainer")

        self.storage = storage
        self.model = None
        self.current_version = None
        self.sequence_length = 10  # Use last 10 candles for prediction

    def train(self, data: List[Dict], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Train LSTM model

        Args:
            data: List of normalized features
            config: Training configuration (epochs, batch_size, etc.)

        Returns:
            Training results with metrics
        """
        try:
            # Extract configuration
            epochs = config.get('epochs', 50)
            batch_size = config.get('batch_size', 32)
            validation_split = config.get('validation_split', 0.2)

            # Prepare sequences
            X, y = self._prepare_sequences(data)

            if len(X) == 0:
                return {
                    'success': False,
                    'error': 'Insufficient data for training'
                }

            # Build model
            self.model = self._build_model(X.shape[1], X.shape[2])

            # Early stopping
            early_stop = EarlyStopping(
                monitor='val_loss',
                patience=10,
                restore_best_weights=True
            )

            # Train model
            history = self.model.fit(
                X, y,
                epochs=epochs,
                batch_size=batch_size,
                validation_split=validation_split,
                callbacks=[early_stop],
                verbose=1
            )

            # Generate model version
            self.current_version = self._generate_version(config)

            # Save model
            temp_path = f"/tmp/{self.current_version}.h5"
            self.model.save(temp_path)
            self.storage.save(self.current_version, temp_path)
            os.remove(temp_path)

            # Calculate metrics
            metrics = {
                'train_loss': float(history.history['loss'][-1]),
                'val_loss': float(history.history['val_loss'][-1]),
                'epochs_trained': len(history.history['loss']),
            }

            return {
                'success': True,
                'model_version': self.current_version,
                'metrics': metrics,
                'dataset_type': 'train',
                'data_points': len(X),
            }

        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }

    def predict(self, features: List[Dict], model_version: Optional[str] = None) -> Dict[str, Any]:
        """
        Make predictions

        Args:
            features: Input features
            model_version: Model version to use

        Returns:
            Predictions
        """
        try:
            # Load model if needed
            if model_version and model_version != self.current_version:
                if not self.load_model(model_version):
                    return {'success': False, 'error': 'Model not found'}

            if self.model is None:
                return {'success': False, 'error': 'No model loaded'}

            # Prepare input
            X = self._prepare_prediction_input(features)

            # Make prediction
            predictions = self.model.predict(X)

            return {
                'success': True,
                'predictions': predictions.tolist(),
                'model_version': self.current_version,
            }

        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }

    def get_model_info(self, model_version: Optional[str] = None) -> Dict[str, Any]:
        """Get model information"""
        version = model_version or self.current_version

        if not version:
            return {'success': False, 'error': 'No model version specified'}

        exists = self.storage.exists(version)

        return {
            'success': True,
            'model_version': version,
            'exists': exists,
            'architecture': 'LSTM',
            'is_loaded': self.current_version == version and self.model is not None,
        }

    def save_model(self, model_version: str) -> bool:
        """Save current model"""
        if self.model is None:
            return False

        try:
            temp_path = f"/tmp/{model_version}.h5"
            self.model.save(temp_path)
            result = self.storage.save(model_version, temp_path)
            os.remove(temp_path)
            return result
        except Exception as e:
            print(f"Error saving model: {e}")
            return False

    def load_model(self, model_version: str) -> bool:
        """Load model from storage"""
        try:
            temp_path = f"/tmp/{model_version}.h5"

            if not self.storage.load(model_version, temp_path):
                return False

            self.model = load_model(temp_path)
            self.current_version = model_version
            os.remove(temp_path)
            return True

        except Exception as e:
            print(f"Error loading model: {e}")
            return False

    def _build_model(self, sequence_length: int, n_features: int) -> Sequential:
        """Build LSTM model architecture"""
        model = Sequential([
            LSTM(50, activation='relu', return_sequences=True,
                 input_shape=(sequence_length, n_features)),
            Dropout(0.2),
            LSTM(50, activation='relu'),
            Dropout(0.2),
            Dense(25, activation='relu'),
            Dense(1)  # Predict next close price
        ])

        model.compile(
            optimizer='adam',
            loss='mse',
            metrics=['mae']
        )

        return model

    def _prepare_sequences(self, data: List[Dict]) -> tuple:
        """Prepare sequences for LSTM training"""
        if len(data) < self.sequence_length + 1:
            return np.array([]), np.array([])

        # Extract features
        feature_keys = ['close_norm', 'volume_norm', 'high_norm', 'low_norm']

        sequences = []
        targets = []

        for i in range(len(data) - self.sequence_length):
            # Sequence of features
            seq = []
            for j in range(i, i + self.sequence_length):
                features = [data[j].get(key, 0) for key in feature_keys]
                seq.append(features)

            # Target is next close price (normalized)
            target = data[i + self.sequence_length].get('close_norm', 0)

            sequences.append(seq)
            targets.append(target)

        return np.array(sequences), np.array(targets)

    def _prepare_prediction_input(self, features: List[Dict]) -> np.ndarray:
        """Prepare input for prediction"""
        feature_keys = ['close_norm', 'volume_norm', 'high_norm', 'low_norm']

        # Take last sequence_length features
        features = features[-self.sequence_length:]

        seq = []
        for f in features:
            seq.append([f.get(key, 0) for key in feature_keys])

        return np.array([seq])  # Add batch dimension

    def _generate_version(self, config: Dict) -> str:
        """Generate model version string"""
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        symbol = config.get('symbol', 'UNKNOWN')
        interval = config.get('interval', '1h')
        return f"lstm_{symbol}_{interval}_{timestamp}"

