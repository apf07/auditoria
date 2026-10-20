"""Inicio local. También admite dependencias instaladas con pip --target .deps."""
import sys
from pathlib import Path

root = Path(__file__).resolve().parent
if (root / '.deps').is_dir():
    sys.path.insert(0, str(root / '.deps'))

if __name__ == '__main__':
    import uvicorn
    uvicorn.run('backend.main:app', host='127.0.0.1', port=8000, log_level='info')
