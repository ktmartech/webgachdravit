<?php
declare(strict_types=1);

namespace Dravit\Core\Menu;

defined('ABSPATH') || exit;

/**
 * Walker & Helper render Mobile Drawer Accordion (`#drv-mobile-menu-drawer`, docs/19 §2.2 & ADR D-35).
 */
final class MobileAccordionWalker extends WalkerNavMenuBase {
    /**
     * @param string   $output
     * @param int      $depth
     * @param object   $args
     */
    public function start_lvl(&$output, $depth = 0, $args = null): void {
        unset($args);
        if ($depth === 0) {
            $output .= '<div class="drv-mob-accordion__body"><div class="drv-mob-sublist">';
            return;
        }
        $output .= '<div class="drv-mob-sublist">';
    }

    /**
     * @param string   $output
     * @param int      $depth
     * @param object   $args
     */
    public function end_lvl(&$output, $depth = 0, $args = null): void {
        unset($args);
        if ($depth === 0) {
            $output .= '</div></div>';
            return;
        }
        $output .= '</div>';
    }

    /**
     * @param string   $output
     * @param object   $data_object
     * @param int      $depth
     * @param object   $args
     * @param int      $current_object_id
     */
    public function start_el(&$output, $data_object, $depth = 0, $args = null, $current_object_id = 0): void {
        unset($current_object_id);
        $item_id      = isset($data_object->ID) ? (int) $data_object->ID : 0;
        $title        = isset($data_object->title) ? (string) $data_object->title : '';
        $raw_url      = isset($data_object->url) ? (string) $data_object->url : '/gach';
        $url          = MenuSetup::sanitize_menu_url($raw_url, true);
        $has_children = (is_object($args) && isset($args->walker) && is_object($args->walker) && !empty($args->walker->has_children))
            || !empty($this->has_children);
        $icon         = ($item_id > 0 && function_exists('get_post_meta')) ? trim((string) get_post_meta($item_id, '_drv_icon', true)) : '';

        if ($depth === 0 && $has_children) {
            $output .= '<details class="drv-mob-accordion">';
            $output .= '<summary class="drv-mob-accordion__summary">';
            $output .= '<span class="l-cluster l-cluster--xs">';
            if ($icon !== '') {
                $output .= '<i class="ph ' . esc_attr($icon) . ' drv-icon" aria-hidden="true"></i>';
            }
            $output .= '<span>' . esc_html($title) . '</span>';
            $output .= '</span>';
            $output .= '<i class="ph ph-caret-down drv-mob-accordion__caret" aria-hidden="true"></i>';
            $output .= '</summary>';
            return;
        }

        $output .= '<a href="' . esc_url($url) . '" class="drv-mob-sublink">';
        $output .= '<span>' . esc_html($title) . '</span>';
        $output .= '</a>';
    }

    /**
     * @param string   $output
     * @param object   $data_object
     * @param int      $depth
     * @param object   $args
     */
    public function end_el(&$output, $data_object, $depth = 0, $args = null): void {
        unset($data_object);
        $has_children = (is_object($args) && isset($args->walker) && is_object($args->walker) && !empty($args->walker->has_children))
            || !empty($this->has_children);
        if ($depth === 0 && $has_children) {
            $output .= '</details>';
        }
    }

    public static function render(): string {
        return MegaMenuRenderer::get_fragments()['mobile'];
    }
}

