// theme-colors.js

jQuery(document).ready(function($) {
    
    // Handle color picker change event
    const spinner = document.getElementById('loading-spinner');
    if (spinner) {
        spinner.style.display = 'block';
    }
    setTimeout(function() {
            $.get(ajaxurl, { action: 'get_theme_colors_css' }, function(cssResponse) {
                if (cssResponse.success) {
                    generateColorBlocks(cssResponse.data.cssString);
                }
            });
        }, 250); // Adjust delay as needed

    // Check if the PHP data exists and the condition is true
    if (typeof phpData !== 'undefined' && phpData.runFunction) {
        const colorContainer = document.getElementById('color-variables');
        // Clear previous content
        colorContainer.innerHTML = '';
        setTimeout(function() {
            $.get(ajaxurl, { action: 'get_theme_colors_css' }, function(cssResponse) {
                if (cssResponse.success) {
                    generateColorBlocks(cssResponse.data.cssString);
                }
            });
        }, 250); // Adjust delay as needed
    }

});

function generateColorBlocksDelayed(cssString) {
    // Add a delay of 2 seconds (2000 milliseconds) before fetching the CSS
    // Show loading spinner
    const spinner = document.getElementById('loading-spinner');
    if (spinner) {
        spinner.style.display = 'block';
    }
    setTimeout(function() {
            $.get(ajaxurl, { action: 'get_theme_colors_css' }, function(cssResponse) {
                if (cssResponse.success) {
                    generateColorBlocks(cssResponse.data.cssString);
                }
            });
        }, 2000); // Adjust delay as needed
}

function generateColorBlocks(cssString) {
    //console.log(cssString);
    const colorContainer = document.getElementById('color-variables');

    if (!colorContainer) {
        console.error('Element with ID "color-variables" not found.');
        return;
    }

    // Show loading spinner
    const spinner = document.getElementById('loading-spinner');
    if (spinner) {
        spinner.style.display = 'block';
    }

    // Extract css and build color swatches
    try {
        const rootVariables = parseCSSVariables(cssString);

        // Hide loading spinner
        if (spinner) {
            spinner.style.display = 'none';
        }

        // Clear previous content
        colorContainer.innerHTML = '';

        // Generate HTML for color blocks
        Object.entries(rootVariables).forEach(([mode, variables]) => {
            // Extract the background value from the variables object
            const firstVariableValue = Object.values(variables)[16];
            const modeHeader = document.createElement('h3');
            modeHeader.textContent = `${mode.charAt(0).toUpperCase() + mode.slice(1)} Mode`;
            modeHeader.classList.add('color-mode-header');
            colorContainer.appendChild(modeHeader);

            const modeContainer = document.createElement('div');
            modeContainer.classList.add('color-container');
            modeContainer.classList.add(mode === 'light' ? 'light-mode' : 'dark-mode'); // Add mode-specific class

            // Set the background color of the mode container to the first value
            modeContainer.style.backgroundColor = firstVariableValue;

            // Append the mode container to the color container
            colorContainer.appendChild(modeContainer);

            // Generate color variables
            Object.entries(variables).forEach(([key, value]) => {
                const colorVariable = document.createElement('div');
                colorVariable.classList.add('color-variable');
                colorVariable.setAttribute('data-color', value);
                colorVariable.setAttribute('data-variable', key);

                const colorSwatch = document.createElement('div');
                colorSwatch.classList.add('color-swatch');
                colorSwatch.setAttribute('data-variable', value);
                colorSwatch.style.backgroundColor = value;

                const variableName = document.createElement('span');
                variableName.classList.add('variable-name');
                variableName.setAttribute('data-variable', key);
                variableName.textContent = key; // Display only the variable name

                colorVariable.appendChild(colorSwatch);
                colorVariable.appendChild(variableName);
                modeContainer.appendChild(colorVariable);
            });
        });

        // Add hover functionality to show color code
        Array.from(colorContainer.querySelectorAll('.color-variable')).forEach(variable => {
            variable.addEventListener('mouseenter', function() {
                const color = this.getAttribute('data-color');
                const tooltip = `<div class="color-tooltip">${color}</div>`;
                this.appendChild(createElementFromHTML(tooltip));
            });
            variable.addEventListener('mouseleave', function() {
                const tooltip = this.querySelector('.color-tooltip');
                if (tooltip) {
                    tooltip.remove();
                }
            });
        });

        // Add click functionality to copy variable name to clipboard
        colorContainer.addEventListener('click', function(event) {
            if (event.target.closest('.variable-name')) {
                const variable = event.target.closest('.variable-name').getAttribute('data-variable');
                copyToClipboard(variable);
            }
        });

        // and rgb value copy to clipboard
        colorContainer.addEventListener('click', function(event) {
            if (event.target.closest('.color-swatch')) {
                const variable = event.target.closest('.color-swatch').getAttribute('data-variable');
                copyToClipboard(variable);
            }
        });

        // Hide loading spinner
        if (spinner) {
            spinner.style.display = 'none';
        }
        
    } catch (error) {
        console.error('Error parsing CSS variables:', error);
    } finally {
        // Hide loading spinner
        if (spinner) {
            spinner.style.display = 'none';
        }
    }
}

function parseCSSVariables(cssText) {
    
    if (!cssText) {
        throw new Error("CSS text is undefined or empty");
    }

    // Match the :root selector and its contents
    const rootMatch = cssText.match(/:root\s*{([^}]*)}/);
    if (!rootMatch) {
        throw new Error(":root selector not found in the CSS text");
    }
    
    const rootVariables = {
        light: {},
        dark: {}
    };

    // Match :root {...} block for light mode
    const rootMatchLight = cssText.match(/:root\s*{([^}]*)}/);
    if (rootMatchLight && rootMatchLight[1]) {
        const declarations = rootMatchLight[1].split(';');
        declarations.forEach(declaration => {
            const trimmedDeclaration = declaration.trim();
            if (trimmedDeclaration) {
                const [propertyName, propertyValue] = trimmedDeclaration.split(':');
                const key = propertyName.trim();
                const value = propertyValue.trim();
                rootVariables.light[key] = value;
            }
        });
    }

    // Match @media (prefers-color-scheme: dark) :root {...} block for dark mode
    const darkModeRegex = /@media\s*\(prefers-color-scheme:\s*dark\)\s*{\s*:root\s*{([^}]*)}/;
    const rootMatchDark = cssText.match(darkModeRegex);
    if (rootMatchDark && rootMatchDark[1]) {
        const declarations = rootMatchDark[1].split(';');
        declarations.forEach(declaration => {
            const trimmedDeclaration = declaration.trim();
            if (trimmedDeclaration) {
                const [propertyName, propertyValue] = trimmedDeclaration.split(':');
                const key = propertyName.trim();
                const value = propertyValue.trim();
                rootVariables.dark[key] = value;
            }
        });
    }
    
    return rootVariables;
}

// Helper function to create DOM elements from HTML string
function createElementFromHTML(htmlString) {
    const div = document.createElement('div');
    div.innerHTML = htmlString.trim();
    return div.firstChild;
}

function clearcontent(elementID) { 
    document.getElementById(elementID).innerHTML = ""; 
}

// Helper function to copy text to clipboard
function copyToClipboard(text) {
    let textToCopy;
    
    if (text.startsWith("--")) {
        const encloseWithVar = jQuery('#enclose_with_var').is(':checked'); // Check if checkbox is checked
        textToCopy = encloseWithVar ? `var(${text})` : text;
    } else {
        textToCopy = text;
    }
    const el = document.createElement('textarea');
    el.value = textToCopy;
    document.body.appendChild(el);
    el.select();
    document.execCommand('copy');
    document.body.removeChild(el);

    // Optionally provide feedback to the user
    showToast('Copied to clipboard: ' + textToCopy);
}


function showToast(message, duration = 2500) {
    // Remove any existing toast to prevent duplicates
    jQuery('.admin-toast').remove();

    // Function to display a toast notification
    const toast = jQuery('<div class="admin-toast"></div>');
    toast.text(message); // Set text content

    // Append toast to body
    jQuery('body').append(toast);

    // Calculate position for side display
    const windowHeight = jQuery(window).height();
    const windowWidth = jQuery(window).width();
    const toastHeight = toast.outerHeight();
    const toastWidth = toast.outerWidth();
    const topPosition = (windowHeight - toastHeight) / 2; // Adjust top position as needed
    const leftPosition = (windowWidth - toastWidth) / 2;

    // Set initial position and show toast
    toast.css({
        'top': topPosition + 'px',
        'right': leftPosition + 'px', // Adjust right position as needed
        'display': 'block',
        'position': 'fixed', // Ensure it stays fixed on scroll
        'z-index': '9999',
        'transition': '0.3s',
        'background-color': 'var(--md-sys-color-primary)',
        'color': 'var(--md-sys-color-on-primary)',
        'padding': '40px',
        'border-radius': '15px',
        'box-shadow': '0 2px 5px rgba(0, 0, 0, 0.45)',
    });

    // Fade out and remove toast after duration
    setTimeout(function() {
        toast.fadeOut('slow', function() {
            jQuery(this).remove();
        });
    }, duration);
}
