"""Build independent installable ZIPs using only Python's standard library."""
from pathlib import Path
import argparse
import shutil
import zipfile

ROOT = Path(__file__).resolve().parent.parent
BUILD = ROOT / "build"
BUILD.mkdir(exist_ok=True)
parser = argparse.ArgumentParser()
parser.add_argument('--only', nargs='+', choices=['core', 'theme', 'connectors'], default=['core', 'theme', 'connectors'])
selected = parser.parse_args().only

def archive(folder: Path, filename: str) -> None:
    with zipfile.ZipFile(BUILD / filename, "w", zipfile.ZIP_DEFLATED) as output:
        for path in sorted(folder.rglob("*")):
            if path.is_file():
                output.write(path, Path(folder.name) / path.relative_to(folder))

for name in (("prihod-telegram", "prihod-whatsapp", "prihod-social") if "connectors" in selected else ()):
    target = BUILD / "packages" / name
    (target / "includes").mkdir(parents=True, exist_ok=True)
    shutil.copyfile(ROOT / "extensions" / name / f"{name}.php", target / f"{name}.php")
    source = (ROOT / "extensions/common/class-connector.php").read_text(encoding="utf-8")
    (target / "includes/class-connector.php").write_text(source.replace("'prihod-connect'", f"'{name}'"), encoding="utf-8")
    lock = (ROOT / "prihod/includes/class-lock.php").read_text(encoding="utf-8")
    (target / "includes/class-lock.php").write_text(lock.replace("namespace Prihod;", "namespace PrihodConnect;"), encoding="utf-8")
    shutil.copyfile(ROOT / "prihod/LICENSE.txt", target / "LICENSE.txt")
    shutil.copyfile(ROOT / "extensions/README.md", target / "README.md")
    title = {"prihod-telegram": "Prihod Telegram", "prihod-whatsapp": "Prihod WhatsApp", "prihod-social": "Prihod Social"}[name]
    readme = f"=== {title} ===\nContributors: vatapin\nRequires at least: 6.5\nRequires PHP: 8.0\nStable tag: 0.1.1\nLicense: GPLv2 or later\nLicense URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html\n\nIndependent opt-in notifications and WordPress publications.\n\n== Description ==\n\nWorks without Prihod Core. All sending is disabled by default. Configure the service explicitly and select each publication in the editor. Credentials are stored in WordPress options, never displayed after saving.\n\n== External services ==\n\nTelegram Bot API: public text/link, chat ID; notifications omit personal request data. https://core.telegram.org/bots/api\nWhatsApp Cloud API: approved template, text/link, recipient number and Phone ID; recipient consent required. https://developers.facebook.com/docs/whatsapp/cloud-api/\nFacebook Graph API: public text/link and Page ID. https://developers.facebook.com/docs/pages-api/posts/\nOnly the provider belonging to this plugin is contacted. No contacts until explicitly enabled. Provider account permissions and approved templates are required.\n\n== Installation ==\n\nUpload ZIP, activate, open Settings and configure the provider. Select publication checkbox in the post editor. WordPress Cron must run.\n\n== Privacy ==\n\nOrdinary uninstall retains configuration/job history and does not delete external posts. No executable code is downloaded.\n"
    (target / "readme.txt").write_text(readme, encoding="utf-8")
    archive(target, f"{name}-0.1.1.zip")

if "core" in selected:
    archive(ROOT / "prihod", "prihod-1.0.0-rc.11.zip")
if "theme" in selected:
    archive(ROOT / "prihod-theme", "prihod-theme-0.3.21.zip")
print("Built: " + ", ".join(selected))
