"""
Disk Storage Implementation

Stores models on local filesystem
Single Responsibility: Only handles local file operations
"""

import os
import shutil
from typing import List
from ..contracts.storage import IModelStorage


class DiskStorage(IModelStorage):
    """Local disk storage for ML models"""

    def __init__(self, base_path: str = "/models"):
        self.base_path = base_path
        os.makedirs(base_path, exist_ok=True)

    def save(self, model_version: str, file_path: str) -> bool:
        """Save model to local disk"""
        try:
            destination = self._get_model_path(model_version)
            os.makedirs(os.path.dirname(destination), exist_ok=True)
            shutil.copy2(file_path, destination)
            return True
        except Exception as e:
            print(f"Error saving model to disk: {e}")
            return False

    def load(self, model_version: str, destination_path: str) -> bool:
        """Load model from local disk"""
        try:
            source = self._get_model_path(model_version)
            if not os.path.exists(source):
                return False

            os.makedirs(os.path.dirname(destination_path), exist_ok=True)
            shutil.copy2(source, destination_path)
            return True
        except Exception as e:
            print(f"Error loading model from disk: {e}")
            return False

    def exists(self, model_version: str) -> bool:
        """Check if model exists on disk"""
        return os.path.exists(self._get_model_path(model_version))

    def delete(self, model_version: str) -> bool:
        """Delete model from disk"""
        try:
            path = self._get_model_path(model_version)
            if os.path.exists(path):
                os.remove(path)
            return True
        except Exception as e:
            print(f"Error deleting model: {e}")
            return False

    def list_models(self) -> List[str]:
        """List all models on disk"""
        try:
            if not os.path.exists(self.base_path):
                return []

            models = []
            for file in os.listdir(self.base_path):
                if file.endswith('.h5') or file.endswith('.pkl'):
                    models.append(file.replace('.h5', '').replace('.pkl', ''))
            return models
        except Exception as e:
            print(f"Error listing models: {e}")
            return []

    def _get_model_path(self, model_version: str) -> str:
        """Get full path for model file"""
        return os.path.join(self.base_path, f"{model_version}.h5")

