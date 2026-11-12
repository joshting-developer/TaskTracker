#!/usr/bin/env python3
"""TaskTracker adapter: selection rules live only in the shared skill registry."""
import os
from pathlib import Path
import runpy
import sys

root = Path(os.environ.get('SIDE_PROJECT_SKILLS_ROOT', '/Users/josh/workspace/side-project/skills'))
script = root / 'tools/select_skills.py'
if not script.is_file():
    raise SystemExit('Shared Skill library missing; restore it before validation')
sys.path.insert(0, str(script.parent))
runpy.run_path(str(script), run_name='__main__')
