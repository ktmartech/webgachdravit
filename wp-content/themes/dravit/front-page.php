<?php
declare(strict_types=1);

use Dravit\Core\Config;
use Dravit\Core\SectionRegistry;

defined('ABSPATH') || exit;

do_action('litespeed_tag_add', 'drv_home');

$home_layout = Config::get('pages/home');

get_header();
?>
<main id="main-content" class="drv-main drv-main--home">
    <?php SectionRegistry::render_stack($home_layout); ?>
</main>
<?php
get_footer();
