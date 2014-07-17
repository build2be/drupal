<?php

/**
 * @file
 * Default theme implementation to display rest documentation section.
 *
 * @see template_preprocess()
 * @see template_preprocess_rest_documentation()
 * @see template_process()
 *
 * @ingroup themeable
 */
?>
<section class="clearfix">

  <h2><?php print $method ?></h2>
  <?php if ($headers): ?>
    <?php print render($headers) ?>
  <?php endif; ?>
</section>
