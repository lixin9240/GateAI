"""混元 GLB 减面导出 — 单 mesh _quadric 简化到目标面数"""
import sys
import trimesh

INPUT = sys.argv[1] if len(sys.argv) > 1 else r"public/models/hunyuan-raw.glb"
OUTPUT = sys.argv[2] if len(sys.argv) > 2 else r"public/models/xiangjiaba-dam.glb"
TARGET = int(sys.argv[3]) if len(sys.argv) > 3 else 30000

print(f"Loading {INPUT} ...")
scene = trimesh.load(INPUT, force="mesh")
if isinstance(scene, trimesh.Scene):
    mesh = trimesh.util.concatenate(tuple(
        g for g in scene.geometry.values() if isinstance(g, trimesh.Trimesh)
    ))
else:
    mesh = scene

print(f"Original: {len(mesh.faces):,} faces, {len(mesh.vertices):,} verts")
print(f"Bounds: {mesh.bounds}")
print(f"Extents: {mesh.extents}")

if len(mesh.faces) <= TARGET:
    print("Already below target, exporting as-is")
    mesh.export(OUTPUT)
    sys.exit(0)

print(f"Simplifying to ~{TARGET:,} faces ...")
simplified = mesh.simplify_quadric_decimation(TARGET)
print(f"Result: {len(simplified.faces):,} faces")

# 居中到原点、统一尺度到坝高约 ~28 单位（与程序化模型对齐）
simplified.apply_translation(-simplified.centroid)
height = simplified.extents[1] if simplified.extents[1] > 0 else max(simplified.extents)
if height > 0:
    scale = 28.0 / height
    simplified.apply_scale(scale)
    print(f"Scaled by {scale:.6f} (target height 28)")

simplified.export(OUTPUT)
print(f"Exported -> {OUTPUT}")
