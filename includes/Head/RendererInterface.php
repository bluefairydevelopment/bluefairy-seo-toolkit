<?php
/**
 * Contract for all <head> renderer classes.
 *
 * @package BlueFairy\SeoToolkit\Head
 */

declare(strict_types=1);

namespace BlueFairy\SeoToolkit\Head;

use BlueFairy\SeoToolkit\PageContext;

/**
 * Implemented by CanonicalRenderer, OpenGraphRenderer, and SchemaRenderer.
 *
 * Each renderer receives an immutable PageContext (built once on the wp hook)
 * and returns HTML to inject into <head>. Returning an empty string '' signals
 * to HeadManager that there is nothing to output for this page — HeadManager
 * will skip echoing and move to the next renderer.
 */
interface RendererInterface {

    /**
     * The feature key this renderer belongs to.
     *
     * Must match one of the keys in the bfst_features option:
     * 'schema', 'og', 'canonical', 'sitemap'.
     *
     * HeadManager uses this to check the settings toggle before calling render().
     *
     * @return string
     */
    public function feature_key(): string;

    /**
     * Render HTML output for injection into <head>.
     *
     * @param PageContext $ctx Immutable snapshot of the current page.
     * @return string HTML string ready to echo, or '' to signal nothing to output.
     *               '' causes HeadManager to skip this renderer for this page.
     *               Never return null — always return a string.
     */
    public function render( PageContext $ctx ): string;
}
