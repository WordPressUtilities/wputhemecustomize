Theme Customize
=================

Adds simpler configuration to the theme customizer API

How to install :
---

* Put this folder to your wp-content/plugins/ folder.
* Activate the plugin in "Plugins" admin section.

How to add sections :
---

Put the code below in your theme's functions.php file. Add new sections to your convenance.

```php
add_filter('wpu_theme_customize__sections', 'wpu_theme_customize__sections__mytheme', 10, 1);
function wpu_theme_customize__sections__mytheme($sections) {
    $sections['global'] = array(
        'name' => 'Global', // Section name
        'priority' => 199 // Priority number to organize sections
    );
    return $sections;
}
```

How to add properties :
--

Put the code below in your theme's functions.php file. Add new properties to your convenance.

```php
add_filter('wpu_theme_customize__settings', 'wpu_theme_customize__settings__mytheme', 10, 1);
function wpu_theme_customize__settings__mytheme($settings) {
    $settings['wpu_link_color'] = array(
        'label' => __('Link Color') , // Name of the property
        'default' => '#6699CC', // Default value (optional)
        'section' => 'global', // Admin section
        'css_selector' => 'a', // CSS selector to target
        'css_property' => 'color' // CSS property to target
    );
    return $settings;
}
```


