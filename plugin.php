<?php
/**
* Plugin Name:  Material 3 Colors
* Plugin URI:   https://www.burtonmsp.com/
* Description:  This plugin adds a color picker to set a material theme
* Version:      1.2.1
* Author:       Burton MSP
* Author URI:   https://www.burtonmsp.com/
* License:      GPL v3 or later
* License URI:  https://www.gnu.org/licenses/gpl-3.0.html
**/

/*	Copyright 2024 Burton MSP (https://www.burtonmsp.com/)

		This program is free software; you can redistribute it and/or
    modify it under the terms of the GNU General Public License
    as published by the Free Software Foundation; either version 3
    of the License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// Enqueue scripts and styles
function enqueue_material_color_utilities() {
    // Enqueue JavaScript with localized data
    wp_enqueue_script('material-color-script', plugin_dir_url(__FILE__) . 'js/theme-colors.js', array('jquery'), null, true);

    $cssString = get_option('theme_colors_css');
    wp_localize_script('material-color-script', 'materialColorSettings', array(
        'cssString' => $cssString ? $cssString : ''
    ));

    // Enqueue jQuery UI Slider
    wp_enqueue_script('jquery-ui-slider');

    // Enqueue admin stylesheet
    wp_register_style('style-css', plugin_dir_url(__FILE__) . 'css/plugin-style.css');
    wp_enqueue_style('style-css');

    // Inline script to dynamically load the necessary module and perform operations
    construct_colors();
}
add_action('admin_enqueue_scripts', 'enqueue_material_color_utilities');

// Enqueue material color utilities
function enqueue_material_color_utilities_styles() {
    wp_enqueue_style( 'material-color-utilities-css', 'https://cdnjs.cloudflare.com/ajax/libs/material-components-web/15.0.0-canary.fff4066c6.0/material-components-web.css', array(), null );
}
add_action( 'wp_enqueue_scripts', 'enqueue_material_color_utilities_styles' );

function output_custom_css() {
    // Retrieve the CSS from the database
    $css_option = get_option('theme_colors_css');
    
    // Decode HTML entities
    $css_option = html_entity_decode($css_option);

    // Output the CSS in a <style> tag
    if ($css_option) {
        echo '<style type="text/css">' . $css_option . '</style>';
    }
}
add_action('wp_head', 'output_custom_css');


function construct_colors() {

    // Inline script to dynamically load the necessary module and generate m3 colors from primary color
    $primary_color = get_option('primary_color', '#65558F');
    $ajax_url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('save_theme_colors_nonce');
    $inline_script = "
    <script type='module'>
        import {
            argbFromHex,
            hexFromArgb,
            themeFromSourceColor,
            TonalPalette
        } from 'https://cdn.jsdelivr.net/npm/@importantimport/material-color-utilities@0.2.2-alpha.0/+esm';

        document.addEventListener('DOMContentLoaded', function() {
            const primaryColor = '{$primary_color}';
            const m3ThemeColorsJSON = themeFromSourceColor(argbFromHex(primaryColor), []);
            const primaryHex = hexFromArgb(m3ThemeColorsJSON.schemes.light.primary);
            const primary98 = hexFromArgb(TonalPalette.fromInt(m3ThemeColorsJSON.schemes.light.primary).tone(98));

            //console.log('Theme Colors JSON:', m3ThemeColorsJSON);
            //console.log('Primary Color:', primaryHex);
            //console.log('Primary Tone 98:', primary98);

            // Create FormData
            const formData = new FormData();
            formData.append('action', 'save_theme_colors');
            formData.append('data', JSON.stringify(m3ThemeColorsJSON));
            formData.append('_wpnonce', '{$nonce}');

            // Send the JSON to the server
            fetch('{$ajax_url}', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => console.log(data))
            .catch(error => console.error('Error:', error));
        });
    </script>";

    // Output the inline script
    echo $inline_script;
    
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get the value from the AJAX request
    $enclose_with_var = isset($_POST['enclose_with_var']) ? $_POST['enclose_with_var'] : 100;

    // Update the database option
    if ($enclose_with_var !=100) {
        update_option('enclose_with_var', $enclose_with_var);
    }
    
    $button_corners = isset($_POST['btn-radius']) ? $_POST['btn-radius'] : 100;

    // Update the database option
    if ($button_corners !=100) {
        update_option('button_corners', $button_corners);
    }
}

function save_theme_colors() {
    // Check nonce for security
    check_ajax_referer('save_theme_colors_nonce', '_wpnonce');

    // Check for the data parameter
    if (isset($_POST['data'])) {
        $theme_colors = json_decode(stripslashes($_POST['data']), true);

        construct_colors();

        // Validate nonce
        check_ajax_referer('save_theme_colors_nonce', '_wpnonce');

        // Extract the hex values
        $schemes = $theme_colors['schemes'];
        $light = $schemes['light'];
        $dark = $schemes['dark'];

        // Create the CSS content
		$css_content = ":root {\n";
		foreach ($light as $key => $value) {
			$dashkey = camelCaseToDash($key);
			$rgb = hexToRgb($value & 0xFFFFFF);
			$css_content .= "--md-sys-color-{$dashkey}: rgb({$rgb[0]}, {$rgb[1]}, {$rgb[2]});\n";
		}
		$css_content .= "}\n\n@media (prefers-color-scheme: dark) {\n:root {\n";
		foreach ($dark as $key => $value) {
			$dashkey = camelCaseToDash($key);
			$rgb = hexToRgb($value & 0xFFFFFF);
			$css_content .= "--md-sys-color-{$dashkey}: rgb({$rgb[0]}, {$rgb[1]}, {$rgb[2]});\n";
		}
		$css_content .= "}\n}\n";

        // Save the CSS content to the wp_options table
        $btnBefore = 'button, input[type="button"], input[type="submit"]{border-radius:';
        $btnAfter = 'px!important;}';
        $button_corners = get_option('button_corners'); // grab button radius
        $btncss = $btnBefore . $button_corners . $btnAfter;
        $btnhover = ".button:hover {\n
            background-color: hsl(\n
                calc(var(--md-sys-color-primary-h) * 1deg),\n
                calc(var(--md-sys-color-primary-s) * 1%),\n
                calc(var(--md-sys-color-primary-l) + 10%)\n
            )!important;\n
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3); /* Elevated shadow */\n
            }";

        $css_content .= $btncss;

        $css_content .= $btnhover;
        update_option('theme_colors_css', $css_content);

        // Return a success response
        wp_send_json_success(['message' => 'Theme colors saved successfully!', 'file_path' => $file_path]);
    } else {
        wp_send_json_error(['message' => 'No data received']);
    }
}
add_action('wp_ajax_save_theme_colors', 'save_theme_colors');

// Function to convert camelCase to dash-separated
function camelCaseToDash($camelCase) {
    $pattern = '/(?<=\\w)(?=[A-Z])|(?<=[a-z])(?=[0-9])/'; 
    $kebabCase = preg_replace($pattern, '-', $camelCase); 
    return strtolower($kebabCase);
}

// Function to convert hex value to RGB
function hexToRgb($hex) {
    $hex = str_pad(dechex($hex), 6, '0', STR_PAD_LEFT);
    return [
        hexdec(substr($hex, 0, 2)), // Extract and convert the first two characters
        hexdec(substr($hex, 2, 2)), // Extract and convert the next two characters
        hexdec(substr($hex, 4, 2))  // Extract and convert the last two characters
    ];
}

function enqueue_theme_colors_script() {
    
    wp_enqueue_script('material-color-utilities', 'https://cdn.jsdelivr.net/npm/@importantimport/material-color-utilities@0.2.2-alpha.0/+esm', array(), null, true);
    $primaryColor = get_option('primary_color', '#65558F'); // Default to a hex value
    wp_localize_script('theme-colors', 'themeColors', array(
        'primaryColor' => $primaryColor,
    ));
    
}
add_action('admin_enqueue_scripts', 'enqueue_theme_colors_script');

// Enqueue the WordPress color picker
wp_enqueue_style('wp-color-picker');
wp_enqueue_script('wp-color-picker');

// Add admin menu item and settings page
function custom_theme_settings_page() {
    add_menu_page('Material Colors', 'Material Colors', 'manage_options', 'theme-settings', 'custom_theme_settings_page_html', $icon_url = '', $position = 61);
}
add_action('admin_menu', 'custom_theme_settings_page');

// HTML for theme settings page
function custom_theme_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings
    if (isset($_POST['primary_color'])) {
        update_option('primary_color', sanitize_text_field($_POST['primary_color']));
        //construct_colors();
        ?>
        <div class="updated">
            <p>Settings saved.</p>
        </div>
        <?php
		//regenerate the color palette
        construct_colors();
		//save out the css file
        run_css_gen();
    }

    $primary_color = get_option('primary_color', '#65558F'); // grab saved color or set default material 3
    $enclose_with_var = get_option('enclose_with_var', 'no'); // grab saved wrap checkbox value
    $button_corners = get_option('button_corners', 25); // grab button radius
    ?>

    <div class="wrap">
        <h1>Material Colors</h1>
        <div>
            <p>This simple plugin generates the Material 3 colors based on your chosen primary color</p>
            <p>You can see the rgb code when hovering each swatch</p>
            <p><b>Copy</b></p>
            <p class="list">
            <span class="dot"></span><span class="litem">Click a color swatch to copy the rgb to clipboard</span></br />
            <span class="dot"></span><span class="litem">Click a variable name to copy it to clipboard</span>
            </p>
            <p><b>Enclose with Var()</b>: When checked, this will wrap the copied text, i.e. --md-sys-color-primary becomes <b>var(--md-sys-color-primary)</b></p>
        </div>
        <form method="post">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="primary_color">Primary Color</label></th>
                    <td>
                        <div id="color_picker_container">
                            <input type="text" id="primary_color" class="color-picker" data-default-color="#65558F" name="primary_color" value="<?php echo esc_attr($primary_color); ?>"/>
                        </div>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="enclose_with_var">Enclose with var()</label></th>
                    <td>
                        <input type="checkbox" id="enclose_with_var" name="enclose_with_var" value="yes" <?php checked($enclose_with_var, 'yes'); ?>/>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Button Corners</th>
                    <td>
                        <div id="button-corners-slider">
                            <div class="button-slider">
                            <input type="hidden" class="btn-radius" id="btn-radius" name="btn-radius" value="<?php echo esc_attr($button_corners); ?>"></input>
                            <input type="range" min="0" max="50" value="<?php echo esc_attr($button_corners); ?>" class="slider" id="button-corners" name="button-corners">
                            
                            </div>
                            <!-- Add a placeholder for the button preview -->
                            <div class="button-preview" style="border-radius: <?php echo esc_attr($button_corners); ?>px;"><span class="btn-text">Button: </span><span id="demo"></span><span class="btn-text">px</span></div>
                        </div>
                    </td>
                </tr>
            </table>
                    
            <?php submit_button('Save Changes', 'primary', 'submit', false); ?>
        </form>

        <script>
            var slider = document.getElementById("button-corners");
            var output = document.getElementById("demo");
            var svebtn = document.getElementById("btn-radius");
            output.innerHTML = slider.value;

            slider.oninput = function() {
                output.innerHTML = this.value;
                // Update button preview image dynamically
                jQuery('.button-preview').css('border-radius', this.value + 'px');
                svebtn.value = this.value; // Update hidden input
            }
        </script>

        <script>
            jQuery(function($) {
                // Initialize slider
                $( "#button-corners-slider" ).slider({
                    range: "min",
                    min: 0,
                    max: 50, // Adjust max value as needed
                    
                    slide: function(event, ui) {
                        $("#demo").val(ui.value).trigger('change'); // Update hidden input
                        
                        // Update button preview image dynamically
                        $('.button-preview').css('border-radius', ui.value + 'px');
                    }
                });
            });
        </script>

        <h2>Generated Theme Colors</h2>
        <div id="loading-spinner" style="display: none;">
            <?php echo'<img src= "'.plugin_dir_url(__FILE__).'loading.gif"; alt="Loading..." />'; ?>
        </div>
        <div id="color-variables"></div>
        <pre id="generated-theme-colors"></pre>

    </div>

    <script>
        jQuery(document).ready(function($){

            /* Call the Color Picker */
            $( ".color-picker" ).wpColorPicker();
        
        });
    </script>

    <script type="module">
        import {
            argbFromHex,
            hexFromArgb,
            themeFromSourceColor,
            applyTheme,
            TonalPalette
        } from 'https://cdn.jsdelivr.net/npm/@importantimport/material-color-utilities@0.2.2-alpha.0/+esm';

        document.addEventListener('DOMContentLoaded', function() {
            const primaryColor = '<?php echo $primary_color; ?>';
            const m3ThemeColorsJSON = themeFromSourceColor(argbFromHex(primaryColor), []);

            // Apply the theme colors
            applyTheme(m3ThemeColorsJSON, { target: document.body });

            // Output theme colors as JSON
            const themeColorsOutput = document.getElementById('generated-theme-colors');
            themeColorsOutput.textContent = JSON.stringify(m3ThemeColorsJSON, null, 2);
        });
    </script>
<?php }

function run_css_gen() {
    wp_enqueue_script('my-custom-js', plugin_dir_url(__FILE__) . 'js/theme-colors.js', array('jquery'), null, true);

    // Pass data to JavaScript
    $data_to_pass = array(
        'runFunction' => true 
    );
    wp_localize_script('my-custom-js', 'phpData', $data_to_pass);
}
add_action('wp_enqueue_scripts', 'run_css_gen');

function handle_custom_submit_action() {

    wp_send_json_success('Custom action completed successfully.');
}
add_action('wp_ajax_custom_submit_action', 'handle_custom_submit_action');
add_action('wp_ajax_nopriv_custom_submit_action', 'handle_custom_submit_action'); // For non-logged-in users

// Function to output the CSS stored in wp_options
function enqueue_theme_colors_css() {
    $css_content = get_option('theme_colors_css');
    if ($css_content) {
        echo '<style id="theme-colors-css">' . esc_html($css_content) . '</style>';
    }
}

// Hook the function to wp_head to include the CSS in the page head
add_action('wp_head', 'enqueue_theme_colors_css');

function get_theme_colors_css() {
    $cssString = get_option('theme_colors_css');
    if ($cssString) {
        wp_send_json_success(['cssString' => $cssString]);
    } else {
        wp_send_json_error(['message' => 'CSS string not found.']);
    }
}
add_action('wp_ajax_get_theme_colors_css', 'get_theme_colors_css');
