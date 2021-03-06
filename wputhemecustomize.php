<?php

/*
Plugin Name: WPU Theme Customize
Plugin URI: https://github.com/Darklg/WPUtilities
Description: Advanced customization for theme
Version: 0.7.2
Author: Darklg
Author URI: http://darklg.me/
License: MIT License
License URI: http://opensource.org/licenses/MIT
*/

class WPUCustomizeTheme {
    function __construct() {

        // Init some values
        $upload_dir = wp_upload_dir();
        $this->up_dir = $upload_dir['basedir'] . '/wputheme-cache';
        $this->up_url = $upload_dir['baseurl'] . '/wputheme-cache';
        $this->css_file = $this->up_dir . '/theme-customizer.css';
        $this->css_file_url = $this->up_url . '/theme-customizer.css';
        $this->cache_file = $this->up_dir . '/theme-customizer.js';
        $this->cache_file_url = $this->up_url . '/theme-customizer.js';
        $this->cached_code_version = get_option('wputh_customize_theme_version');

        // Set events
        add_action('init', array(&$this,
            'init'
        ));
        add_action('customize_register', array(&$this,
            'wpu_customize_theme'
        ));
        add_action('customize_preview_init', array(&$this,
            'customizer_live_preview'
        ));
        add_action('customize_save_after', array(&$this,
            'customize_save_after'
        ));
        add_action('after_switch_theme', array(&$this,
            'customize_save_after'
        ));
        add_action('current_screen', array(&$this,
            'regenerate_js_file'
        ));
        add_action('wp_enqueue_scripts', array(&$this,
            'wp_enqueue_modifications'
        ) , 999);
    }

    // Load filters
    function init() {
        $this->sections = apply_filters('wpu_theme_customize__sections', array());
        $this->settings = apply_filters('wpu_theme_customize__settings', array());
    }

    // Function regenerate_js_file
    function regenerate_js_file() {
        $screen = get_current_screen();
        if (!isset($screen->base) || $screen->base != 'customize') {
            return;
        }

        // Check cache directory
        if (!is_dir($this->up_dir)) {
            @mkdir($this->up_dir, 0777);
            @chmod($this->up_dir, 0777);
        }

        // Check code version
        $str_sections = serialize($this->sections);
        $str_settings = serialize($this->settings);
        $code_version = md5($str_settings . $str_sections);

        if ($this->cached_code_version !== $code_version) {
            $content_cache = "(function($) {\n" . "var wputhcu_setval = function(selector,property,val) {" . "if(property == 'background-image'){" . "val = 'url('+val+')';" . "}" . "$(selector).css(property, val);" . "};\n";
            foreach ($this->settings as $id => $setting) {
                $tmp_id = 'wputheme_' . $id;
                $property = trim(esc_attr($setting['css_property']));
                $selector = trim(esc_attr($setting['css_selector']));
                if (!empty($property) && !empty($selector)) {
                    $content_cache.= "wp.customize('" . $tmp_id . "', function(value) {" . "value.bind(function(val){" . "wputhcu_setval('" . $selector . "','" . $property . "',val);" . "});});\n";
                }
            }
            $content_cache.= "})(jQuery);\n";

            // Regenerate JS file
            file_put_contents($this->cache_file, $content_cache);

            // Set code version
            $this->cached_code_version = $code_version;
            update_option('wputh_customize_theme_version', $code_version);
        }
    }

    // Display theme modifications in front-end
    function generate_theme_css() {
        $content = '';
        foreach ($this->settings as $id => $setting) {
            $tmp_id = 'wputheme_' . $id;
            $mod = strtolower(get_theme_mod($tmp_id));
            $def = isset($setting['default']) ? strtolower($setting['default']) : '';
            $property = trim(esc_attr($setting['css_property']));
            $selector = trim(esc_attr($setting['css_selector']));
            if (!empty($mod) && !empty($property) && !empty($selector) && $mod != $def) {

                if ($property == 'background-image') {
                    $mod = 'url(' . $mod . ')';
                }

                $content.= $selector . '{' . $property . ':' . $mod . '}';
            }
        }
        return $content;
    }

    function wp_enqueue_modifications() {
        if (is_admin()) {
            return;
        }
        $css = $this->generate_theme_css();
        if (empty($css)) {
            return;
        }
        $code_version = get_option('wputh_customize_theme_css_date');

        wp_register_style('wputhemecustomize', $this->css_file_url, array() , $code_version);
        wp_enqueue_style('wputhemecustomize');
    }

    // Register functions
    function wpu_customize_theme($wp_customize) {

        foreach ($this->sections as $id => $section) {
            $wp_customize->add_section('wputh_' . $id, array(
                'title' => $section['name'],
                'priority' => isset($section['priority']) ? $section['priority'] : 200
            ));
        }

        foreach ($this->settings as $id => $setting) {
            if (!isset($setting['css_property'])) {
                $setting['css_property'] = '';
            }
            if (!isset($setting['default'])) {
                $setting['default'] = '';
            }
            $detail_setting = array(
                'default' => $setting['default'],
                'type' => 'theme_mod',
                'capability' => 'edit_theme_options',
                'transport' => 'postMessage',
            );
            $detail_control = array(
                'label' => $setting['label'],
                'section' => 'wputh_' . $setting['section'],
                'settings' => 'wputheme_' . $id,
                'priority' => 10,
            );

            /* Special types */

            /* text-align */
            if ($setting['css_property'] == 'text-align') {
                if (empty($detail_setting['default'])) {
                    $detail_setting['default'] = 'left';
                }
                $detail_control['type'] = 'select';
                $detail_control['choices'] = array(
                    'left' => 'left',
                    'center' => 'center',
                    'justify' => 'justify',
                    'right' => 'right',
                );
            }

            /* font-size */
            if ($setting['css_property'] == 'font-size') {
                $detail_control['type'] = 'select';
                $detail_control['choices'] = array();
                for ($i = 8;$i <= 70;$i++) {
                    $detail_control['choices'][$i . 'px'] = $i . 'px';
                }
                if (empty($detail_setting['default']) || !array_key_exists($detail_setting['default'], $detail_control['choices'])) {
                    $detail_setting['default'] = '15px';
                }
            }

            if ($setting['css_property'] == 'font-weight') {
                $detail_control['type'] = 'select';
                $detail_control['choices'] = array(
                    '100' => '100',
                    '200' => '200',
                    '300' => '300',
                    '400' => 'normal / 400',
                    '500' => '500 / book',
                    '600' => '600 / semibold',
                    '700' => '700 / bold',
                    '800' => '800',
                    '900' => '900',
                    'initial' => 'initial',
                    'inherit' => 'inherit',
                );
                if (empty($detail_setting['default']) || !array_key_exists($detail_setting['default'], $detail_control['choices'])) {
                    $detail_setting['default'] = '400';
                }
            }

            /* line-height */
            if ($setting['css_property'] == 'line-height') {
                $detail_control['type'] = 'select';
                $detail_control['choices'] = array();
                $detail_setting['default'] = $detail_setting['default'] * 10;
                for ($i = 1;$i <= 50;$i++) {
                    $detail_control['choices'][$i] = ($i / 10);
                }
                if (empty($detail_setting['default']) || !array_key_exists($detail_setting['default'], $detail_control['choices'])) {
                    $detail_setting['default'] = '1.5';
                }
            }

            $wp_customize->add_setting('wputheme_' . $id, $detail_setting);
            switch ($setting['css_property']) {
                case 'color':
                case 'background-color':
                    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'wputheme_' . $id, $detail_control));
                break;
                case 'background-image':
                    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'wputheme_' . $id, $detail_control));
                break;
                default:
                    $wp_customize->add_control(new WP_Customize_Control($wp_customize, 'wputheme_' . $id, $detail_control));
            }
        }

        return $wp_customize;
    }

    // Enable live preview in admin
    function customizer_live_preview() {
        wp_enqueue_script('wputheme-themecustomizer', $this->cache_file_url, array(
            'jquery',
            'customize-preview'
        ) , $this->cached_code_version, true);
    }

    function customize_save_after() {

        // generate css
        $css = '@charset "UTF-8";' . $this->generate_theme_css();
        file_put_contents($this->css_file, $css);

        // Save latest update
        update_option('wputh_customize_theme_css_date', time());
    }
}

$WPUCustomizeTheme = new WPUCustomizeTheme();

