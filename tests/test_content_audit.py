"""Regression checks for the read-only migration audit's HTML extraction."""
from pathlib import Path
import sys
import unittest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.audit_site_content import Page, safe_url


class ContentAuditTest(unittest.TestCase):
    def test_article_excludes_navigation_sidebar_and_hidden_text(self):
        page = Page()
        page.feed('<header>Menu</header><article class="wp-block-post-content">'
                  '<p>Article <img src="photo.jpg">continues</p>'
                  '<script>not prose</script><span style="display: none">hidden</span>'
                  '</article><aside>Sidebar</aside>')
        self.assertEqual('Article continues', page.result()['article'])
        self.assertEqual(['photo.jpg'], page.result()['images'])

    def test_nested_legacy_article_and_media_after_void_elements(self):
        page = Page()
        page.feed('<div class="post-content"><div><p>Before<br>After</p>'
                  '<video><source src="clip.mp4"></video>'
                  '<iframe data-src="https://www.youtube.com/embed/abc"></iframe>'
                  '<a href="/fotos/?type_0=gallery&amp;album_gallery_id_0=4">Album</a>'
                  '</div></div><footer>Outside</footer>')
        result = page.result()
        self.assertEqual('Before After Album', result['article'])
        self.assertIn('clip.mp4', result['media'])
        self.assertIn('https://www.youtube.com/embed/abc', result['media'])
        self.assertEqual(['/fotos/?type_0=gallery&album_gallery_id_0=4'], result['article_links'])

    def test_url_encoding_preserves_existing_escapes_and_query(self):
        self.assertEqual('https://example.org/%D0%A5%D1%80%D0%B0%D0%BC/%d0%b0/?p=2',
                         safe_url('https://example.org/Храм/%d0%b0/?p=2#section'))


if __name__ == '__main__':
    unittest.main()
