"""
Trainer Contract (Abstract Interface)

Defines the interface for ML trainers
Follows Dependency Inversion Principle
"""

from abc import ABC, abstractmethod
from typing import Dict, List, Any, Optional


class ITrainer(ABC):
    """
    Abstract base class for ML trainers

    Single Responsibility: Define training interface
    Open/Closed: Can be extended without modification
    """

    @abstractmethod
    def train(self, data: List[Dict], config: Dict[str, Any]) -> Dict[str, Any]:
        """
        Train a model

        Args:
            data: Training data (normalized features)
            config: Training configuration

        Returns:
            Dictionary with training results, model version, metrics
        """
        pass

    @abstractmethod
    def predict(self, features: List[Dict], model_version: Optional[str] = None) -> Dict[str, Any]:
        """
        Make predictions

        Args:
            features: Input features
            model_version: Specific model version to use (None for latest)

        Returns:
            Dictionary with predictions and confidence scores
        """
        pass

    @abstractmethod
    def get_model_info(self, model_version: Optional[str] = None) -> Dict[str, Any]:
        """
        Get model information

        Args:
            model_version: Specific model version (None for latest)

        Returns:
            Dictionary with model metadata
        """
        pass

    @abstractmethod
    def save_model(self, model_version: str) -> bool:
        """
        Save trained model

        Args:
            model_version: Model identifier

        Returns:
            Success status
        """
        pass

    @abstractmethod
    def load_model(self, model_version: str) -> bool:
        """
        Load trained model

        Args:
            model_version: Model identifier

        Returns:
            Success status
        """
        pass

