#!/usr/bin/env python3
"""Ilustraciones SVG originales de ejemplo; rsvg-convert produce PNG para Drupal.

Solo se usa en desarrollo. La demo incluye los PNG ya generados y no necesita
Python ni librsvg dentro de sus contenedores.
"""
import json
import subprocess
from html import escape
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'drupal/assets/products'


def drawing(kind, color, label, size):
    ink = '#234b31'
    common = f'<g stroke="{ink}" stroke-width="3" stroke-linejoin="round">'
    if kind == 'food':
        return common + f'<path d="M160 86h160l20 280H140Z" fill="{color}"/><path d="M160 86h160l-8 27H168Z" fill="#ffffff55"/><path d="M151 195h178v107H151Z" fill="#fffdf6"/><text x="240" y="236" text-anchor="middle" stroke="none" fill="{ink}" font-size="18" font-weight="bold">{label}</text><text x="240" y="275" text-anchor="middle" stroke="none" fill="{ink}" font-size="16">{size}</text><path d="m170 125-12 225M310 125l12 225" opacity=".15"/></g>'
    if kind == 'bottle':
        return common + f'<rect x="205" y="77" width="70" height="40" rx="6" fill="{ink}"/><rect x="180" y="111" width="120" height="249" rx="32" fill="{color}"/><rect x="181" y="196" width="118" height="101" rx="3" fill="#fffdf6"/><text x="240" y="239" text-anchor="middle" stroke="none" font-size="19" fill="{ink}">{label}</text><text x="240" y="271" text-anchor="middle" stroke="none" font-size="15" fill="{ink}">{size}</text></g>'
    if kind == 'collar':
        return common + f'<ellipse cx="240" cy="225" rx="113" ry="86" fill="none" stroke="{color}" stroke-width="40"/><ellipse cx="240" cy="225" rx="113" ry="86" fill="none" stroke="#ffffff55" stroke-width="2" stroke-dasharray="5 7"/><rect x="262" y="282" width="54" height="43" rx="8" fill="#f4e4b1"/><path d="M288 284v39"/><circle cx="211" cy="315" r="18" fill="#f2c94c"/><path d="M211 308v14m-6-7h12"/></g>'
    if kind == 'bowl':
        return common + f'<path d="m111 199 21 117q108 54 216 0l21-117Z" fill="{color}"/><ellipse cx="240" cy="197" rx="130" ry="44" fill="#dce2da"/><ellipse cx="240" cy="198" rx="103" ry="27" fill="#b2c0b0"/><path d="M147 248q93 45 186 0" stroke="#fffdf6" stroke-width="9" opacity=".7"/></g>'
    if kind == 'brush':
        return common + f'<g transform="rotate(25 240 230)"><rect x="220" y="234" width="40" height="135" rx="18" fill="{color}"/><rect x="167" y="100" width="146" height="154" rx="42" fill="{color}"/><rect x="181" y="114" width="118" height="124" rx="30" fill="#ede7d8"/>' + ''.join(f'<circle cx="{x}" cy="{y}" r="3.5" fill="{ink}" stroke="none"/>' for y in range(139,220,20) for x in range(200,290,20)) + '</g></g>'
    if kind == 'ball':
        return common + f'<circle cx="240" cy="227" r="112" fill="{color}"/><path d="M151 159q136 10 145 157M185 129q-19 145 125 181" fill="none" stroke="#fff4cf" stroke-width="14"/><circle cx="204" cy="183" r="21" fill="#ffffff33" stroke="none"/></g>'
    if kind == 'toy':
        return common + f'<path d="m142 334 139-207" stroke="{ink}" stroke-width="12"/><path d="M281 127q60-25 43 56t-65 44" fill="none" stroke-width="3"/><path d="M258 224q-77-25-55 48 60 22 55-48Z" fill="{color}"/><path d="M257 225q78-15 43 51-59 7-43-51Z" fill="#d6b762"/><path d="m258 224-25 54"/></g>'
    if kind == 'cone':
        return common + f'<path d="M127 145q113-46 226 0l-47 173q-66 34-132 0Z" fill="{color}" opacity=".85"/><ellipse cx="240" cy="143" rx="113" ry="37" fill="#e5eee1"/><ellipse cx="240" cy="316" rx="66" ry="25" fill="#fffdf6"/><path d="m143 160 42 137m152-137-42 137" opacity=".4"/></g>'
    return common + f'<rect x="143" y="135" width="194" height="170" rx="12" fill="{color}"/><path d="M143 159h194v76H143Z" fill="#fffdf6"/><path d="M232 173h16v15h15v16h-15v15h-16v-15h-15v-16h15Z" fill="{color}" stroke="none"/><text x="240" y="270" text-anchor="middle" stroke="none" fill="#fffdf6" font-size="21">{label}</text></g>'


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    for item in json.loads((ROOT / 'scripts/data/products.json').read_text()):
        label, size = escape(item['label']), escape(item['size'])
        svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 480 440"><title>Ilustración de ejemplo: {escape(item['name'])}</title><rect width="480" height="440" fill="#f4f2e9"/><circle cx="240" cy="220" r="173" fill="#e9eadf"/><ellipse cx="240" cy="372" rx="115" ry="14" fill="#244b3210"/>{drawing(item['kind'],item['color'],label,size)}<text x="240" y="414" font-family="sans-serif" font-size="10" letter-spacing="2" fill="#667460" text-anchor="middle">IMAGEN REFERENCIAL · DEMO</text></svg>'''
        source = OUT / (item['sku'] + '.svg')
        source.write_text(svg)
        subprocess.run(['rsvg-convert', '-w', '720', '-h', '660', '-o', str(OUT / (item['sku'] + '.png')), str(source)], check=True)
    print('Generadas 15 ilustraciones locales y sus PNG para el campo de imagen.')


if __name__ == '__main__':
    main()
