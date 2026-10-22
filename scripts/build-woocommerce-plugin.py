#!/usr/bin/env python3
"""Rebuild the private, authenticated dashboard download from plugin sources."""
from pathlib import Path
import zipfile

root = Path(__file__).resolve().parents[1]
source = root / 'integrations/woocommerce/raslo-woocommerce'
target = root / 'resources/integrations/raslo-woocommerce.zip'
target.parent.mkdir(parents=True, exist_ok=True)
with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED) as archive:
    for path in sorted(source.rglob('*')):
        if path.is_file() and path.suffix in {'.php', '.txt'}:
            info = zipfile.ZipInfo(str(Path('raslo-woocommerce') / path.relative_to(source)))
            info.date_time = (2026, 10, 22, 0, 0, 0)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes())
print(target)
