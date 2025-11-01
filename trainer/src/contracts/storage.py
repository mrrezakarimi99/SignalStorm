"""
Model Storage Contract (Abstract Interface)

Defines the interface for model storage strategies
"""

from abc import ABC, abstractmethod
from typing import Optional, List


class IModelStorage(ABC):
    """
    Abstract base class for model storage

    Allows switching between local disk, MinIO, S3, etc.
    """

    @abstractmethod
    def save(self, model_version: str, file_path: str) -> bool:
        """
        Save model file to storage

        Args:
            model_version: Model identifier
            file_path: Local file path to save

        Returns:
            Success status
        """
        pass

    @abstractmethod
    def load(self, model_version: str, destination_path: str) -> bool:
        """
        Load model file from storage

        Args:
            model_version: Model identifier
            destination_path: Local path to save loaded model

        Returns:
            Success status
        """
        pass

    @abstractmethod
    def exists(self, model_version: str) -> bool:
        """
        Check if model exists in storage

        Args:
            model_version: Model identifier

        Returns:
            True if exists
        """
        pass

    @abstractmethod
    def delete(self, model_version: str) -> bool:
        """
        Delete model from storage

        Args:
            model_version: Model identifier

        Returns:
            Success status
        """
        pass

    @abstractmethod
    def list_models(self) -> List[str]:
        """
        List all stored models

        Returns:
            List of model versions
        """
        pass

