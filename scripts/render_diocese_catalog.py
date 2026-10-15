"""Use the packaged DOCX rasterizer with the PDF exported by native Word.

Windows has Word but no LibreOffice. Only the conversion function is replaced;
page images and PDF handling still use the packaged render_docx.py pipeline.
"""
import importlib.util, sys, tempfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
(ROOT/'tmp/diocese-qa/temp').mkdir(parents=True,exist_ok=True)
tempfile.tempdir=str(ROOT/'tmp/diocese-qa/temp')
skill=Path('C:/Users/atapi/.codex/plugins/cache/openai-primary-runtime/documents/26.1013.11914/skills/documents/render_docx.py')
spec=importlib.util.spec_from_file_location('render_docx',skill)
renderer=importlib.util.module_from_spec(spec);spec.loader.exec_module(renderer)
for name in ['Prihod_proposal','Prihod_brief','Diocese_sites_appendix']:
    docx=ROOT/'docs/diocese-catalog'/f'{name}.docx'
    pdf=docx.with_suffix('.pdf')
    def native_pdf(*args,**kwargs):return str(pdf),'Exported by installed Microsoft Word 16.0'
    renderer.convert_to_pdf=native_pdf
    sys.argv=[str(skill),str(docx),'--output_dir',str(ROOT/'tmp/diocese-qa'/name),'--dpi','110']
    renderer.main()
