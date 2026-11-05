<?php
/**
 * Title: Homepage
 * Slug: georg-kloster-prihod/homepage
 * Categories: featured
 * Block Types: core/post-content
 */
?>
<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:site-title {"level":1} /-->
<!-- wp:site-tagline /-->
<!-- wp:heading --><h2 class="wp-block-heading"><?php esc_html_e( 'News', 'georg-kloster-prihod' ); ?></h2><!-- /wp:heading -->
<!-- wp:query {"queryId":0,"query":{"perPage":3,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true} /-->
<!-- wp:post-title {"isLink":true,"level":3} /-->
<!-- wp:post-excerpt /-->
<!-- /wp:post-template --></div><!-- /wp:query -->
</div><!-- /wp:group -->
