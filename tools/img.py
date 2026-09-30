#!/usr/bin/env python3
"""Prepare images for the web: crop to 16:9 (centre), resize to 1600x900, save as WebP.

Usage: img.py [input_dir] [output_dir] [--size 1600x900] [--quality 80] [--no-crop]
Defaults: obrazky/vstup -> obrazky/web. Originals are never modified.
"""
import argparse, os, sys
from PIL import Image, ImageOps

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..')
ap = argparse.ArgumentParser()
ap.add_argument('src', nargs='?', default=os.path.join(ROOT, 'obrazky', 'vstup'))
ap.add_argument('dst', nargs='?', default=os.path.join(ROOT, 'obrazky', 'web'))
ap.add_argument('--size', default='1600x900')
ap.add_argument('--quality', type=int, default=80)
ap.add_argument('--no-crop', action='store_true', help='jen zmenšit, neořezávat na poměr')
a = ap.parse_args()
w, h = map(int, a.size.split('x'))
os.makedirs(a.dst, exist_ok=True)

for name in sorted(os.listdir(a.src)):
    if not name.lower().endswith(('.png', '.jpg', '.jpeg', '.webp')):
        continue
    src = os.path.join(a.src, name)
    im = ImageOps.exif_transpose(Image.open(src)).convert('RGB')
    im = im.copy() if a.no_crop else ImageOps.fit(im, (w, h), Image.LANCZOS, centering=(0.5, 0.5))
    if a.no_crop:
        im.thumbnail((w, h), Image.LANCZOS)
    out = os.path.join(a.dst, os.path.splitext(name)[0] + '.webp')
    im.save(out, 'WEBP', quality=a.quality, method=6)
    print(f"{name}: {os.path.getsize(src)//1024} kB -> {os.path.basename(out)} {im.size[0]}x{im.size[1]} {os.path.getsize(out)//1024} kB")
