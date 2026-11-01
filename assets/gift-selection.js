/**
 * Gift Selection Handler - Manages product gift selection with variation support
 * Version: 2.0 - Attribute-based selection
 */
jQuery(document).ready(function($) {
    var __ = wp.i18n.__;
    // Prevent modal/popup initialization on gift selection container
    const container = $('.mmd-gift-selection-container');

    if (container.length > 0) {
        // Remove any modal-related classes that might be added by theme
        container.removeClass('modal').removeClass('popup').removeClass('dialog');
        container.removeAttr('data-modal').removeAttr('data-popup').removeAttr('data-dialog');

        // Ensure container stays in normal document flow
        container.css({
            'display': 'block',
            'position': 'static',
            'visibility': 'visible',
            'opacity': '1'
        });

        // Reposition: move gift container right before form.cart if it's not already near it
        var $form = $('form.cart').first();
        if ($form.length && !$form.find('.mmd-gift-selection-container').length) {
            // Check if container is a sibling or inside the same parent as form
            var containerParent = container.parent()[0];
            var formParent = $form.parent()[0];
            // If the container is far from the form (not in the same parent or nearby),
            // move it right before the form
            if (containerParent !== formParent && !$.contains(formParent, container[0])) {
                container.detach().insertBefore($form);
            }
        }
    }

    const selectionType = container.data('selection-type');
    const allowQuantity = container.data('allow-quantity') === 'yes';
    const maxQuantity = parseInt(container.data('max-quantity')) || 5;
    const totalQuantityLimit = parseInt(container.data('total-quantity-limit')) || 0; // 0 = no limit
    let selectedGifts = {}; // Track selected gifts: { productId: variationId or 0 } or { productId: {variation_id, quantity} }

    // Ensure hidden gift fields exist in the form
    function ensureGiftFieldsExist() {
        var form = $('form.cart');
        if (!form.length) return;
        if (!form.find('#mmd_selected_gift_product').length) {
            form.append('<input type="hidden" name="mmd_selected_gift_product" id="mmd_selected_gift_product" value="">');
        }
        if (!form.find('#mmd_selected_gift_variation').length) {
            form.append('<input type="hidden" name="mmd_selected_gift_variation" id="mmd_selected_gift_variation" value="">');
        }
        if (!form.find('#mmd_selected_gift_attributes').length) {
            form.append('<input type="hidden" name="mmd_selected_gift_attributes" id="mmd_selected_gift_attributes" value="">');
        }
        if (!form.find('#mmd_selected_gifts_container').length) {
            form.append('<div id="mmd_selected_gifts_container"></div>');
        }
    }

    // Create fields on init
    ensureGiftFieldsExist();

    // Helper function to calculate total selected quantity
    function getTotalSelectedQuantity() {
        let total = 0;
        for (let key in selectedGifts) {
            const gift = selectedGifts[key];
            if (typeof gift === 'object' && gift.quantity) {
                total += gift.quantity;
            } else if (typeof gift === 'object' && !gift.product_id) {
                // Old format without explicit quantity
                total += 1;
            } else if (typeof gift === 'number' || gift === 0) {
                // Simple product ID format
                total += 1;
            }
        }
        return total;
    }

    // Display total quantity indicator if limit is set
    function updateQuantityIndicator() {
        if (totalQuantityLimit > 0 && allowQuantity && selectionType === 'multiple') {
            const currentTotal = getTotalSelectedQuantity();
            let indicator = $('.mmd-total-quantity-indicator');

            if (!indicator.length) {
                container.find('.mmd-gift-subtitle').after(
                    '<div class="mmd-total-quantity-indicator" style="background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 4px; margin-bottom: 10px; font-size: 14px;">' +
                    '<span class="mmd-quantity-current">0</span> / <span class="mmd-quantity-limit">' + totalQuantityLimit + ( '</span> ' + __( 'Selected', 'incentiv' ) ) +
                    '</div>'
                );
                indicator = $('.mmd-total-quantity-indicator');
            }

            indicator.find('.mmd-quantity-current').text(currentTotal);

            // Visual feedback when approaching limit
            if (currentTotal >= totalQuantityLimit) {
                indicator.css('background', 'rgba(76, 175, 80, 0.3)');
            } else if (currentTotal >= totalQuantityLimit * 0.8) {
                indicator.css('background', 'rgba(255, 193, 7, 0.3)');
            } else {
                indicator.css('background', 'rgba(255,255,255,0.2)');
            }
        }
    }

    // Handle +/- buttons for quantity inputs
    $(document).on('click', '.mmd-quantity-plus', function() {
        const input = $(this).siblings('.mmd-quantity-input-wrapper').find('input[type="number"]');
        const currentVal = parseInt(input.val()) || 0;
        const max = parseInt(input.attr('max')) || 999;

        if (currentVal < max) {
            input.val(currentVal + 1).trigger('change');
        }
    });

    $(document).on('click', '.mmd-quantity-minus', function() {
        const input = $(this).siblings('.mmd-quantity-input-wrapper').find('input[type="number"]');
        const currentVal = parseInt(input.val()) || 0;
        const min = parseInt(input.attr('min')) || 0;

        if (currentVal > min) {
            input.val(currentVal - 1).trigger('change');
        }
    });

    // Update button states based on quantity value
    $(document).on('input change', '.mmd-gift-quantity-input, .mmd-gift-variation-quantity-input', function() {
        const input = $(this);
        const currentVal = parseInt(input.val()) || 0;
        const min = parseInt(input.attr('min')) || 0;
        const max = parseInt(input.attr('max')) || 999;
        const quantityControl = input.closest('.mmd-quantity-control');

        // Update minus button state
        quantityControl.find('.mmd-quantity-minus').prop('disabled', currentVal <= min);

        // Update plus button state
        quantityControl.find('.mmd-quantity-plus').prop('disabled', currentVal >= max);
    });

    // Handle quantity inputs (when allow-quantity is enabled)
    if (allowQuantity && selectionType === 'multiple') {
        // Handle variation-specific quantity inputs (for expanded variations)
        $(document).on('change', '.mmd-gift-variation-quantity-input', function() {
            const productId = $(this).data('product-id');
            const variationId = $(this).data('variation-id');
            const requestedQuantity = parseInt($(this).val()) || 0;
            const productRow = $(this).closest('.mmd-gift-product-row');
            const attributes = $(this).data('attributes') || {};

            // Create a unique key for this variation
            const selectionKey = productId + '_' + variationId;

            // Check total quantity limit
            if (totalQuantityLimit > 0 && requestedQuantity > 0) {
                const currentTotal = getTotalSelectedQuantity();
                const previousQuantity = selectedGifts[selectionKey] ? (selectedGifts[selectionKey].quantity || 0) : 0;
                const quantityDelta = requestedQuantity - previousQuantity;
                const newTotal = currentTotal + quantityDelta;

                if (newTotal > totalQuantityLimit) {
                    const maxAllowed = totalQuantityLimit - (currentTotal - previousQuantity);
                    if (maxAllowed > 0) {
                        alert(( __( 'You can choose up to', 'incentiv' ) + ' ' ) + totalQuantityLimit + ( ' ' + __( 'gifts in total. Remaining:', 'incentiv' ) + ' ' ) + maxAllowed + ( ' ' + __( 'units.', 'incentiv' ) ));
                        $(this).val(maxAllowed);
                        const quantity = maxAllowed;
                        selectedGifts[selectionKey] = {
                            product_id: productId,
                            variation_id: variationId,
                            quantity: quantity,
                            attributes: attributes
                        };
                    } else {
                        alert(__( 'You have reached the gift limit (', 'incentiv' ) + totalQuantityLimit + ')');
                        $(this).val(0);
                        delete selectedGifts[selectionKey];
                    }
                    productRow.toggleClass('selected', selectedGifts[selectionKey] !== undefined);
                    updateHiddenFields();
                    updateQuantityIndicator();
                    return;
                }
            }

            if (requestedQuantity > 0) {
                selectedGifts[selectionKey] = {
                    product_id: productId,
                    variation_id: variationId,
                    quantity: requestedQuantity,
                    attributes: attributes
                };
                productRow.addClass('selected');
            } else {
                delete selectedGifts[selectionKey];
                productRow.removeClass('selected');
            }

            updateHiddenFields();
            updateQuantityIndicator();
            console.log('Variation quantity changed for product', productId, 'variation', variationId, ':', requestedQuantity);
        });

        // Handle product-level quantity inputs (for simple products and variable with selected variations)
        $(document).on('change', '.mmd-gift-quantity-input', function() {
            const productId = $(this).data('product-id');
            const requestedQuantity = parseInt($(this).val()) || 0;
            const productRow = $(this).closest('.mmd-gift-product-row');
            const isVariable = productRow.data('variable') === 1;

            // Check total quantity limit first
            if (totalQuantityLimit > 0 && requestedQuantity > 0) {
                const currentTotal = getTotalSelectedQuantity();
                const previousQuantity = selectedGifts[productId] ? (selectedGifts[productId].quantity || 1) : 0;
                const quantityDelta = requestedQuantity - previousQuantity;
                const newTotal = currentTotal + quantityDelta;

                if (newTotal > totalQuantityLimit) {
                    const maxAllowed = totalQuantityLimit - (currentTotal - previousQuantity);
                    if (maxAllowed > 0) {
                        alert(( __( 'You can choose up to', 'incentiv' ) + ' ' ) + totalQuantityLimit + ( ' ' + __( 'gifts in total. Remaining:', 'incentiv' ) + ' ' ) + maxAllowed + ( ' ' + __( 'units.', 'incentiv' ) ));
                        $(this).val(maxAllowed);
                    } else {
                        alert(__( 'You have reached the gift limit (', 'incentiv' ) + totalQuantityLimit + ')');
                        $(this).val(0);
                        updateQuantityIndicator();
                        return;
                    }
                }
            }

            const quantity = parseInt($(this).val()) || 0;

            if (quantity > 0) {
                // Quantity selected
                if (isVariable) {
                    // For variable products, check if variation is already selected
                    const variationsContainer = productRow.find('.mmd-variation-selectors');
                    if (!variationsContainer.data('loaded')) {
                        // Variations not loaded yet - auto-trigger the selection button
                        const selectBtn = productRow.find('.mmd-gift-variable-btn');
                        if (selectBtn.length) {
                            selectBtn.trigger('click');
                            // Keep the quantity value the user entered
                            $(this).val(quantity);
                        }
                        return;
                    }

                    // Check if variation is fully selected
                    if (!selectedGifts[productId] || !selectedGifts[productId].variation_id) {
                        alert(__( 'Please select a variation first', 'incentiv' ));
                        $(this).val(0);
                        return;
                    }

                    // Update quantity for already selected variation
                    selectedGifts[productId].quantity = quantity;
                } else {
                    // Simple product
                    selectedGifts[productId] = {
                        variation_id: 0,
                        quantity: quantity,
                        attributes: {}
                    };
                }
                productRow.addClass('selected');
            } else {
                // Quantity cleared
                delete selectedGifts[productId];
                productRow.removeClass('selected');
            }

            updateHiddenFields();
            updateQuantityIndicator();
            console.log('Quantity changed for product', productId, ':', quantity);
        });

        // For variable products in quantity mode, handle variation selection differently
        $(document).on('change', '.mmd-variation-attribute', function() {
            const variationsContainer = $(this).closest('.mmd-variation-selectors');
            const productRow = variationsContainer.closest('.mmd-gift-product-row');
            const productId = productRow.data('product-id');
            const quantityInput = productRow.find('.mmd-gift-quantity-input');

            // Check if all attributes selected
            const allSelected = checkAllAttributesSelected(variationsContainer);
            if (allSelected) {
                const selectedAttributes = getSelectedAttributes(variationsContainer);
                const variations = variationsContainer.data('variations');
                const matchedVariation = findMatchingVariation(variations, selectedAttributes);

                if (matchedVariation) {
                    // Update selected gifts with variation info
                    if (!selectedGifts[productId]) {
                        selectedGifts[productId] = { variation_id: 0, quantity: 0, attributes: {} };
                    }
                    selectedGifts[productId].variation_id = matchedVariation.variation_id;
                    selectedGifts[productId].attributes = selectedAttributes;

                    // If quantity already set, update hidden fields
                    if (selectedGifts[productId].quantity > 0) {
                        updateHiddenFields();
                    }

                    console.log('Variation selected for product', productId, ':', matchedVariation.variation_id);
                }
            }
        });
    }

    // Handle simple product selection
    $(document).on('click', '.mmd-gift-select-btn[data-simple-product]', function() {
        const productId = $(this).data('simple-product');
        const productRow = $(this).closest('.mmd-gift-product-row');

        if (selectionType === 'single') {
            // Single selection mode
            if (selectedGifts[productId]) {
                // Deselect if already selected
                delete selectedGifts[productId];
                productRow.removeClass('selected');
                $(this).removeClass('selected').text(__( 'Choose', 'incentiv' ));
            } else {
                // Clear all previous selections
                selectedGifts = {};
                $('.mmd-gift-product-row').removeClass('selected');
                $('.mmd-gift-select-btn').removeClass('selected').text(__( 'Choose', 'incentiv' ));

                // Get quantity from input if available
                const quantityInput = productRow.find('.mmd-gift-quantity-input');
                const quantity = quantityInput.length ? parseInt(quantityInput.val()) || 1 : 1;

                // Select this one
                if (allowQuantity) {
                    selectedGifts[productId] = {
                        variation_id: 0,
                        quantity: quantity,
                        attributes: {}
                    };
                } else {
                    selectedGifts[productId] = 0;
                }
                productRow.addClass('selected');
                $(this).addClass('selected').text(__( '✓ Selected', 'incentiv' ));
            }

            // Update hidden fields for single mode
            $('#mmd_selected_gift_product').val(productId);
            $('#mmd_selected_gift_variation').val('');
        } else {
            // Multiple selection mode
            if (selectedGifts[productId]) {
                // Deselect
                delete selectedGifts[productId];
                productRow.removeClass('selected');
                $(this).removeClass('selected').text(__( 'Choose', 'incentiv' ));
            } else {
                // Select
                selectedGifts[productId] = 0;
                productRow.addClass('selected');
                $(this).addClass('selected').text(__( '✓ Selected', 'incentiv' ));
            }
        }

        updateHiddenFields();
    });

    // Handle pre-filtered variation selection (from attribute-filtered gift products)
    $(document).on('click', '.mmd-gift-variation-select-btn', function() {
        const $btn = $(this);
        const $row = $btn.closest('.mmd-gift-product-row');
        const productId = $btn.data('product-id');
        const variationId = $btn.data('variation-id');
        const presetAttributes = $btn.data('preset-attributes') || {};

        ensureGiftFieldsExist();

        // Check if there are remaining attribute selects that need values
        const $remainingSelects = $row.find('.mmd-variation-remaining-select');
        if ($remainingSelects.length > 0) {
            let allFilled = true;
            const mergedAttributes = Object.assign({}, presetAttributes);

            $remainingSelects.each(function() {
                const val = $(this).val();
                const attrKey = $(this).data('attribute-key');
                if (!val) {
                    allFilled = false;
                    $(this).css('border-color', 'red');
                    setTimeout(() => { $(this).css('border-color', ''); }, 2000);
                } else {
                    mergedAttributes[attrKey] = val;
                }
            });

            if (!allFilled) return;

            // All attributes selected - proceed with merged attributes
            if (selectionType === 'single') {
                selectedGifts = {};
                $('.mmd-gift-product-row').removeClass('selected');
                $('.mmd-gift-select-btn').removeClass('selected').text(__( 'Choose', 'incentiv' ));

                selectedGifts[productId] = {
                    variation_id: variationId,
                    quantity: 1,
                    attributes: mergedAttributes
                };
                $row.addClass('selected');
                $btn.addClass('selected').text(__( '✓ Selected', 'incentiv' ));

                $('#mmd_selected_gift_product').val(productId);
                $('#mmd_selected_gift_variation').val(variationId);
                $('#mmd_selected_gift_attributes').val(JSON.stringify(mergedAttributes));
            } else {
                if (selectedGifts[variationId]) {
                    delete selectedGifts[variationId];
                    $row.removeClass('selected');
                    $btn.removeClass('selected').text(__( 'Choose', 'incentiv' ));
                } else {
                    selectedGifts[variationId] = {
                        variation_id: variationId,
                        quantity: 1,
                        attributes: mergedAttributes
                    };
                    $row.addClass('selected');
                    $btn.addClass('selected').text(__( '✓ Selected', 'incentiv' ));
                }
            }
        } else {
            // No remaining attributes - variation is fully specified
            if (selectionType === 'single') {
                selectedGifts = {};
                $('.mmd-gift-product-row').removeClass('selected');
                $('.mmd-gift-select-btn').removeClass('selected').text(__( 'Choose', 'incentiv' ));

                selectedGifts[productId] = {
                    variation_id: variationId,
                    quantity: 1,
                    attributes: presetAttributes
                };
                $row.addClass('selected');
                $btn.addClass('selected').text(__( '✓ Selected', 'incentiv' ));

                $('#mmd_selected_gift_product').val(productId);
                $('#mmd_selected_gift_variation').val(variationId);
                $('#mmd_selected_gift_attributes').val(JSON.stringify(presetAttributes));
            } else {
                if (selectedGifts[variationId]) {
                    delete selectedGifts[variationId];
                    $row.removeClass('selected');
                    $btn.removeClass('selected').text(__( 'Choose', 'incentiv' ));
                } else {
                    selectedGifts[variationId] = {
                        variation_id: variationId,
                        quantity: 1,
                        attributes: presetAttributes
                    };
                    $row.addClass('selected');
                    $btn.addClass('selected').text(__( '✓ Selected', 'incentiv' ));
                }
            }
        }

        updateHiddenFields();
        updateQuantityIndicator();
    });

    // Handle variable product selection - show variations inline
    $(document).on('click', '.mmd-gift-variable-btn', function() {
        const productRow = $(this).closest('.mmd-gift-product-row');
        const productId = productRow.data('product-id');
        const variationsContainer = productRow.find('.mmd-variation-selectors');
        const button = $(this);

        console.log('Loading variations for product:', productId);

        // If already showing, this will enable/confirm selection
        if (variationsContainer.is(':visible') && variationsContainer.data('loaded')) {
            // Check if all attributes are selected
            const allSelected = checkAllAttributesSelected(variationsContainer);
            if (allSelected) {
                selectVariation(productRow, variationsContainer);
            }
            return;
        }

        // Get preloaded variation data from data attribute
        const variationInfo = productRow.data('variation-info');

        if (!variationInfo || !variationInfo.attributes || !variationInfo.variations) {
            console.error('MMD: No variation data preloaded for product', productId);
            alert(__( 'No options are available for this product', 'incentiv' ));
            return;
        }

        console.log('MMD: Using preloaded variation data');
        console.log('MMD: Building attribute selectors for', variationInfo.attributes.length, 'attributes');
        console.log('MMD: Total variations available:', variationInfo.variations.length);

        // Check if we have attributes
        if (variationInfo.attributes.length === 0) {
            console.error('MMD: No attributes in preloaded data');
            alert(__( 'No options are available for this product', 'incentiv' ));
            return;
        }

        let html = '';
        let hasVisibleSelectors = false;
        let autoSelectAttributes = {};

        // First, analyze all variations to find which attributes have fixed values
        const fixedAttributes = {};
        variationInfo.attributes.forEach(function(attr) {
            const uniqueValues = new Set();

            // Check all variations for this attribute
            variationInfo.variations.forEach(function(variation) {
                const varValue = variation.attributes[attr.name];
                if (varValue !== '') {  // Only count non-empty values
                    uniqueValues.add(varValue);
                }
            });

            // If all variations have the same non-empty value, it's fixed
            if (uniqueValues.size === 1) {
                fixedAttributes[attr.name] = Array.from(uniqueValues)[0];
                console.log('Detected fixed attribute:', attr.name, '=', fixedAttributes[attr.name]);
            }
        });

        // Build attribute selectors inline
        variationInfo.attributes.forEach(function(attr, index) {
            console.log('Building selector for attribute:', attr.name, 'with', attr.options.length, 'options');

            // Check if this attribute has a fixed value across all variations
            if (fixedAttributes[attr.name]) {
                // Pre-select and hide this attribute
                autoSelectAttributes[attr.name] = fixedAttributes[attr.name];
                console.log('Auto-selecting fixed attribute', attr.name, ':', fixedAttributes[attr.name]);
                html += '<input type="hidden" class="mmd-variation-attribute" data-attribute-name="' + attr.name + '" value="' + fixedAttributes[attr.name] + '">';
            } else if (attr.options.length === 1) {
                // Auto-select the only option and hide the selector
                var optionValue = typeof attr.options[0] === 'object' ? attr.options[0].value : attr.options[0];
                autoSelectAttributes[attr.name] = optionValue;
                console.log('Auto-selecting single option for', attr.name, ':', optionValue);
                html += '<input type="hidden" class="mmd-variation-attribute" data-attribute-name="' + attr.name + '" value="' + optionValue + '">';
            } else {
                hasVisibleSelectors = true;
                html += '<div class="mmd-attribute-field">';
                html += '<label>' + attr.label + ':</label>';
                html += '<select class="mmd-variation-attribute" data-attribute-name="' + attr.name + '">';
                html += ( '<option value="">' + __( 'Choose...', 'incentiv' ) + '</option>' );

                attr.options.forEach(function(option) {
                    // Option is now an object with 'value' and 'label'
                    var optionValue = typeof option === 'object' ? option.value : option;
                    var optionLabel = typeof option === 'object' ? option.label : option;
                    html += '<option value="' + optionValue + '">' + optionLabel + '</option>';
                });

                html += '</select>';
                html += '</div>';
            }
        });

        variationsContainer.html(html);

        // Only show container if there are visible selectors
        if (hasVisibleSelectors) {
            variationsContainer.show();
        } else {
            variationsContainer.hide();
        }

        variationsContainer.data('loaded', true);

        // Check for default variation and pre-select it
        const defaultVariationId = productRow.data('default-variation');
        if (defaultVariationId) {
            console.log('Pre-selecting default variation:', defaultVariationId);
            // Find the default variation in variations data
            const defaultVariation = variationInfo.variations.find(v => v.variation_id == defaultVariationId);
            if (defaultVariation) {
                // Pre-select attributes for this variation
                variationsContainer.find('.mmd-variation-attribute').each(function() {
                    const attrName = $(this).data('attribute-name');
                    const attrValue = defaultVariation.attributes[attrName];
                    if (attrValue) {
                        $(this).val(attrValue);
                    }
                });
            }
        }

        // Decode variation attribute values before storing
        const decodedVariations = variationInfo.variations.map(function(variation) {
            const decodedAttrs = {};
            for (let attrName in variation.attributes) {
                const attrValue = variation.attributes[attrName];
                try {
                    decodedAttrs[attrName] = attrValue ? decodeURIComponent(attrValue) : attrValue;
                } catch(e) {
                    decodedAttrs[attrName] = attrValue;
                }
            }
            return {...variation, attributes: decodedAttrs};
        });

        // Store variations data
        variationsContainer.data('variations', decodedVariations);
        variationsContainer.data('product-id', productId);

        // If all attributes are auto-selected (no visible selectors), auto-select the variation
        if (!hasVisibleSelectors && Object.keys(autoSelectAttributes).length > 0) {
            console.log('All attributes auto-selected, selecting variation automatically...');
            button.text(__( '✓ Selected', 'incentiv' )).addClass('selected ready');
            selectVariation(productRow, variationsContainer);
        } else {
            button.text(__( 'Confirm', 'incentiv' ));
            console.log('Variation selectors built and displayed successfully from preloaded data');
        }
    });

    // Helper function to check if all attributes are selected
    function checkAllAttributesSelected(variationsContainer) {
        const attributes = variationsContainer.find('.mmd-variation-attribute');
        let allSelected = true;

        attributes.each(function() {
            if (!$(this).val()) {
                allSelected = false;
                return false; // break
            }
        });

        return allSelected;
    }

    // Helper function to get selected attributes
    function getSelectedAttributes(variationsContainer) {
        const attributes = variationsContainer.find('.mmd-variation-attribute');
        const selectedAttributes = {};

        attributes.each(function() {
            const attrName = $(this).data('attribute-name');
            const attrValue = $(this).val();
            if (attrValue) {
                // Decode URL-encoded attribute values for proper transmission
                try {
                    selectedAttributes[attrName] = decodeURIComponent(attrValue);
                } catch(e) {
                    // If decode fails, use original value
                    selectedAttributes[attrName] = attrValue;
                }
            }
        });

        return selectedAttributes;
    }

    // Helper function to normalize attribute name (handle URL encoding)
    function normalizeAttributeName(attrName) {
        // Decode URL-encoded attribute names for comparison
        try {
            return decodeURIComponent(attrName);
        } catch(e) {
            return attrName;
        }
    }

    // Helper function to find matching variation
    function findMatchingVariation(variationsContainer, selectedAttributes) {
        const variations = variationsContainer.data('variations');

        console.log('Searching through', variations.length, 'variations');
        console.log('Looking for attributes:', selectedAttributes);

        for (let i = 0; i < variations.length; i++) {
            const variation = variations[i];
            let matches = true;

            console.log('Checking variation', variation.variation_id, 'with attributes:', variation.attributes);

            // Check if all selected attributes match the variation
            for (let selectedAttrName in selectedAttributes) {
                const selectedValue = selectedAttributes[selectedAttrName];
                const normalizedSelectedName = normalizeAttributeName(selectedAttrName);
                let foundMatch = false;

                // Check against all variation attributes (handling URL encoding)
                for (let varAttrName in variation.attributes) {
                    const normalizedVarName = normalizeAttributeName(varAttrName);

                    if (normalizedSelectedName === normalizedVarName) {
                        const varValue = variation.attributes[varAttrName];

                        // Empty value in variation means "any option is valid"
                        if (varValue === '' || varValue === selectedValue) {
                            foundMatch = true;
                            break;
                        }
                    }
                }

                if (!foundMatch) {
                    matches = false;
                    break;
                }
            }

            if (matches) {
                console.log('Found matching variation:', variation.variation_id);
                return variation;
            }
        }

        console.warn('No matching variation found for attributes:', selectedAttributes);
        return null;
    }

    // Function to select a variation
    function selectVariation(productRow, variationsContainer) {
        const selectedAttributes = getSelectedAttributes(variationsContainer);
        const matchedVariation = findMatchingVariation(variationsContainer, selectedAttributes);

        if (!matchedVariation) {
            alert(__( 'This combination is not available', 'incentiv' ));
            return;
        }

        if (!matchedVariation.is_in_stock) {
            alert(__( 'This variation is out of stock', 'incentiv' ));
            return;
        }

        const giftProductId = variationsContainer.data('product-id');
        const variationId = matchedVariation.variation_id;

        console.log('Selecting variation:', {
            giftProductId: giftProductId,
            variationId: variationId,
            attributes: selectedAttributes
        });

        if (selectionType === 'single') {
            // Clear all previous selections
            selectedGifts = {};
            $('.mmd-gift-product-row').removeClass('selected');
            $('.mmd-gift-select-btn').removeClass('selected').text(__( 'Choose', 'incentiv' ));

            // Get quantity from input if available
            const quantityInput = productRow.find('.mmd-gift-quantity-input');
            const quantity = quantityInput.length ? parseInt(quantityInput.val()) || 1 : 1;

            // Select this variation
            if (allowQuantity) {
                selectedGifts[giftProductId] = {
                    variation_id: variationId,
                    quantity: quantity,
                    attributes: selectedAttributes
                };
            } else {
                selectedGifts[giftProductId] = variationId;
            }
            productRow.addClass('selected');
            productRow.find('.mmd-gift-select-btn').addClass('selected').text(__( '✓ Selected', 'incentiv' ));

            // Update hidden fields for single mode
            $('#mmd_selected_gift_product').val(giftProductId);
            $('#mmd_selected_gift_variation').val(variationId);

            // Store selected attributes as raw JSON (let serialization handle encoding)
            $('#mmd_selected_gift_attributes').val(JSON.stringify(selectedAttributes));

            console.log('Updated hidden fields - product:', giftProductId, 'variation:', variationId, 'quantity:', quantity, 'attributes:', selectedAttributes);
        } else {
            // Multiple selection mode
            selectedGifts[giftProductId] = {
                variation_id: variationId,
                attributes: selectedAttributes
            };
            productRow.addClass('selected');
            productRow.find('.mmd-gift-select-btn').addClass('selected').text(__( '✓ Selected', 'incentiv' ));
        }

        updateHiddenFields();
    }

    // Handle attribute selection change - auto-select when all attributes are chosen
    $(document).on('change', '.mmd-variation-attribute', function() {
        const productRow = $(this).closest('.mmd-gift-product-row');
        const variationsContainer = $(this).closest('.mmd-variation-selectors');
        const button = productRow.find('.mmd-gift-variable-btn');

        const allSelected = checkAllAttributesSelected(variationsContainer);

        if (allSelected) {
            // Auto-select the variation immediately
            console.log('All attributes selected, auto-selecting variation...');
            button.prop('disabled', false).text(__( 'Confirm', 'incentiv' )).addClass('ready');

            // Automatically select the variation
            selectVariation(productRow, variationsContainer);
        } else {
            button.prop('disabled', false).text(__( 'Confirm', 'incentiv' )).removeClass('ready');
        }
    });

    // Update hidden fields based on selection type
    function updateHiddenFields() {
        if (selectionType === 'multiple') {
            // Clear container
            $('#mmd_selected_gifts_container').empty();

            // Add hidden fields for each selected gift
            let index = 0;
            for (let selectionKey in selectedGifts) {
                const giftData = selectedGifts[selectionKey];

                // Handle new format (with product_id in the object)
                let productId, variationId, attributes, quantity;
                if (typeof giftData === 'object' && giftData.product_id) {
                    // New variation-specific format
                    productId = giftData.product_id;
                    variationId = giftData.variation_id || 0;
                    attributes = giftData.attributes || {};
                    quantity = giftData.quantity || 1;
                } else {
                    // Old format (backward compatibility)
                    productId = selectionKey;
                    variationId = typeof giftData === 'object' ? giftData.variation_id : giftData;
                    attributes = typeof giftData === 'object' ? giftData.attributes : {};
                    quantity = typeof giftData === 'object' && giftData.quantity ? giftData.quantity : 1;
                }

                $('#mmd_selected_gifts_container').append(
                    '<input type="hidden" name="mmd_selected_gift_products[' + index + '][product_id]" value="' + productId + '">' +
                    '<input type="hidden" name="mmd_selected_gift_products[' + index + '][variation_id]" value="' + variationId + '">' +
                    '<input type="hidden" name="mmd_selected_gift_products[' + index + '][quantity]" value="' + quantity + '">' +
                    '<input type="hidden" name="mmd_selected_gift_products[' + index + '][attributes]" value="' + JSON.stringify(attributes) + '">'
                );
                index++;
            }
        }
        // For single mode, fields are already updated in the selection handlers

        console.log('Hidden fields updated. Selection type:', selectionType, 'Allow quantity:', allowQuantity);
        console.log('Gift product:', $('#mmd_selected_gift_product').val());
        console.log('Gift variation:', $('#mmd_selected_gift_variation').val());
        console.log('Gift attributes:', $('#mmd_selected_gift_attributes').val());
    }

    // Visual feedback on add to cart button
    const originalAddToCartForm = $('form.cart');
    if (originalAddToCartForm.length) {
        // Handle form submission validation
        originalAddToCartForm.on('submit', function(e) {
            // Check if gift is enabled and selected
            const giftContainer = $('.mmd-gift-selection-container');
            if (giftContainer.length) {
                const hasSelection = Object.keys(selectedGifts).length > 0;

                // In quantity mode, selection is optional (user can set all to 0)
                if (!hasSelection && !(allowQuantity && selectionType === 'multiple')) {
                    e.preventDefault();
                    alert(__( 'Please choose a gift before adding to cart', 'incentiv' ));
                    giftContainer[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }

                // Validate total quantity limit if set
                if (totalQuantityLimit > 0 && allowQuantity && selectionType === 'multiple') {
                    const currentTotal = getTotalSelectedQuantity();
                    if (currentTotal < totalQuantityLimit) {
                        e.preventDefault();
                        alert(( __( 'Please choose', 'incentiv' ) + ' ' ) + totalQuantityLimit + ( ' ' + __( 'gifts. Selected:', 'incentiv' ) + ' ' ) + currentTotal + ( ' ' + __( 'of', 'incentiv' ) + ' ' ) + totalQuantityLimit);
                        giftContainer[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    } else if (currentTotal > totalQuantityLimit) {
                        e.preventDefault();
                        alert(__( 'Too many gifts selected (', 'incentiv' ) + currentTotal + ( __( '). The maximum is', 'incentiv' ) + ' ' ) + totalQuantityLimit);
                        giftContainer[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                }

                // Ensure hidden fields are set just before submission
                if (hasSelection) {
                    ensureHiddenFieldsSet();
                }

                console.log('Form submitting with gift selection:', selectedGifts);
                console.log('Form data being sent - Product:', $('#mmd_selected_gift_product').val(), 'Variation:', $('#mmd_selected_gift_variation').val());
            }
        });

        // Handle WooCommerce AJAX add to cart for variable products
        // This needs to run BEFORE WooCommerce's handler
        $(document).on('click', '.single_add_to_cart_button:not(.disabled)', function(e) {
            const giftContainer = $('.mmd-gift-selection-container');
            if (giftContainer.length) {
                const hasSelection = Object.keys(selectedGifts).length > 0;

                // In quantity mode, selection is optional (user can set all to 0)
                if (!hasSelection && !(allowQuantity && selectionType === 'multiple')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    alert(__( 'Please choose a gift before adding to cart', 'incentiv' ));
                    giftContainer[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }

                // Validate total quantity limit if set
                if (totalQuantityLimit > 0 && allowQuantity && selectionType === 'multiple') {
                    const currentTotal = getTotalSelectedQuantity();
                    if (currentTotal < totalQuantityLimit) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        alert(( __( 'Please choose', 'incentiv' ) + ' ' ) + totalQuantityLimit + ( ' ' + __( 'gifts. Selected:', 'incentiv' ) + ' ' ) + currentTotal + ( ' ' + __( 'of', 'incentiv' ) + ' ' ) + totalQuantityLimit);
                        giftContainer[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    } else if (currentTotal > totalQuantityLimit) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        alert(__( 'Too many gifts selected (', 'incentiv' ) + currentTotal + ( __( '). The maximum is', 'incentiv' ) + ' ' ) + totalQuantityLimit);
                        giftContainer[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                }

                // Ensure hidden fields are set just before WooCommerce processes the request
                if (hasSelection) {
                    ensureHiddenFieldsSet();
                }

                console.log('Add to cart clicked with gift selection:', selectedGifts);
                console.log('Form data being sent - Product:', $('#mmd_selected_gift_product').val(), 'Variation:', $('#mmd_selected_gift_variation').val());

                // Log the actual form data that will be sent
                const formData = originalAddToCartForm.serializeArray();
                console.log('Serialized form data:', formData);
                const giftProduct = formData.find(item => item.name === 'mmd_selected_gift_product');
                const giftVariation = formData.find(item => item.name === 'mmd_selected_gift_variation');
                console.log('Gift product in form data:', giftProduct ? giftProduct.value : 'NOT FOUND');
                console.log('Gift variation in form data:', giftVariation ? giftVariation.value : 'NOT FOUND');
            }
        });
    }

    // WoodMart AJAX Add to Cart Compatibility
    // Hook into WoodMart's AJAX add to cart to ensure gift data is included
    $(document).on('click', '.single_add_to_cart_button', function(e) {
        const giftContainer = $('.mmd-gift-selection-container');

        if (giftContainer.length) {
            // Ensure hidden fields are set before any AJAX processing
            ensureHiddenFieldsSet();

            // Store gift data in a global variable that can be accessed by AJAX handlers
            window.mmdGiftSelectionData = {
                product: $('#mmd_selected_gift_product').val(),
                variation: $('#mmd_selected_gift_variation').val(),
                attributes: $('#mmd_selected_gift_attributes').val()
            };

            console.log('MMD Gift: Prepared gift data for AJAX:', window.mmdGiftSelectionData);
        }
    });

    // Hook into WooCommerce's add to cart AJAX to append gift data
    $(document).on('adding_to_cart', function(e, $button, data) {
        console.log('MMD Gift: WooCommerce adding_to_cart event fired');
        const giftContainer = $('.mmd-gift-selection-container');

        if (giftContainer.length && window.mmdGiftSelectionData) {
            // Add gift data to the AJAX request
            if (window.mmdGiftSelectionData.product) {
                data.mmd_selected_gift_product = window.mmdGiftSelectionData.product;
                console.log('MMD Gift: Added gift product to AJAX data:', window.mmdGiftSelectionData.product);
            }
            if (window.mmdGiftSelectionData.variation) {
                data.mmd_selected_gift_variation = window.mmdGiftSelectionData.variation;
                console.log('MMD Gift: Added gift variation to AJAX data:', window.mmdGiftSelectionData.variation);
            }
            if (window.mmdGiftSelectionData.attributes) {
                data.mmd_selected_gift_attributes = window.mmdGiftSelectionData.attributes;
                console.log('MMD Gift: Added gift attributes to AJAX data');
            }

            // Also add individual gift attributes for compatibility
                    const attributesJson = window.mmdGiftSelectionData.attributes;
                    if (attributesJson) {
                        const attributes = parseGiftAttributes(attributesJson);

                        if (attributes) {
                            for (let attrName in attributes) {
                                const attrValue = attributes[attrName];
                                data['gift_' + attrName] = attrValue;
                                console.log('MMD Gift: Added individual gift attribute:', 'gift_' + attrName, '=', attrValue);
                            }
                        } else {
                            console.error('MMD Gift: Error parsing gift attributes for AJAX');
                        }
                    }
        }
    });

    // Additional hook: Intercept AJAX calls globally to add gift data
    // This works for themes that use their own AJAX handlers (like WoodMart)
    if (typeof $.ajaxPrefilter !== 'undefined') {
        $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
            // More comprehensive detection of add-to-cart requests
            const isAddToCartRequest =
                (options.url && (
                    options.url.indexOf('?add-to-cart=') > -1 ||
                    options.url.indexOf('wc-ajax=add_to_cart') > -1 ||
                    options.url.indexOf('&add-to-cart=') > -1
                )) ||
                (options.data && typeof options.data === 'string' && (
                    options.data.indexOf('add-to-cart') > -1 ||
                    options.data.indexOf('add_to_cart') > -1
                )) ||
                (options.data && typeof options.data === 'object' && (
                    options.data['add-to-cart'] ||
                    options.data['add_to_cart']
                ));

            if (isAddToCartRequest) {
                const giftContainer = $('.mmd-gift-selection-container');

                if (giftContainer.length && window.mmdGiftSelectionData) {
                    console.log('MMD Gift: AJAX prefilter - Intercepted add to cart request');
                    console.log('MMD Gift: Request URL:', options.url);
                    console.log('MMD Gift: Request method:', options.type);

                    // Convert data to object/string without re-encoding existing params
                    let dataObj = {};
                    let dataString = '';

                    if (typeof options.data === 'string') {
                        // Keep the original string intact to avoid double-encoding attribute keys
                        dataString = options.data;
                    } else if (typeof options.data === 'object' && options.data !== null) {
                        dataObj = Object.assign({}, options.data);
                    } else if (options.data instanceof FormData) {
                        // Handle FormData objects (used by some themes)
                        console.log('MMD Gift: AJAX data is FormData, appending gift data');
                        if (window.mmdGiftSelectionData.product && !options.data.has('mmd_selected_gift_product')) {
                            options.data.append('mmd_selected_gift_product', window.mmdGiftSelectionData.product);
                        }
                        if (window.mmdGiftSelectionData.variation && !options.data.has('mmd_selected_gift_variation')) {
                            options.data.append('mmd_selected_gift_variation', window.mmdGiftSelectionData.variation);
                        }
                        if (window.mmdGiftSelectionData.attributes && !options.data.has('mmd_selected_gift_attributes')) {
                            options.data.append('mmd_selected_gift_attributes', window.mmdGiftSelectionData.attributes);
                        }
                        console.log('MMD Gift: AJAX prefilter - Added gift data to FormData');
                        return; // Exit early for FormData
                    }

                    // Add gift data to the request
                    if (typeof originalOptions.data === 'string' || typeof options.data === 'string') {
                        // Append to query string while preserving existing encoding
                        const separator = dataString && !dataString.endsWith('&') ? '&' : '';
                        if (window.mmdGiftSelectionData.product) {
                            dataString += separator + 'mmd_selected_gift_product=' + encodeURIComponent(window.mmdGiftSelectionData.product);
                        }
                        if (window.mmdGiftSelectionData.variation) {
                            dataString += (dataString && !dataString.endsWith('&') ? '&' : '') + 'mmd_selected_gift_variation=' + encodeURIComponent(window.mmdGiftSelectionData.variation);
                        }
                        if (window.mmdGiftSelectionData.attributes) {
                            dataString += (dataString && !dataString.endsWith('&') ? '&' : '') + 'mmd_selected_gift_attributes=' + encodeURIComponent(window.mmdGiftSelectionData.attributes);
                        }

                        options.data = dataString;
                        console.log('MMD Gift: Converted data to query string');
                    } else {
                        if (window.mmdGiftSelectionData.product) {
                            dataObj.mmd_selected_gift_product = window.mmdGiftSelectionData.product;
                            console.log('MMD Gift: Added gift product to AJAX data:', window.mmdGiftSelectionData.product);
                        }
                        if (window.mmdGiftSelectionData.variation) {
                            dataObj.mmd_selected_gift_variation = window.mmdGiftSelectionData.variation;
                            console.log('MMD Gift: Added gift variation to AJAX data:', window.mmdGiftSelectionData.variation);
                        }
                        if (window.mmdGiftSelectionData.attributes) {
                            dataObj.mmd_selected_gift_attributes = window.mmdGiftSelectionData.attributes;
                            console.log('MMD Gift: Added gift attributes to AJAX data');
                            console.log('MMD Gift: Attributes value:', window.mmdGiftSelectionData.attributes);
                        }

                        options.data = dataObj;
                    }

                    console.log('MMD Gift: AJAX prefilter - Final modified data:', options.data);
                }
            }
        });
    }

    // Safely parse gift attributes JSON that may be raw or URL-encoded
    function parseGiftAttributes(attributesJson) {
        if (!attributesJson) {
            return null;
        }

        try {
            return JSON.parse(attributesJson);
        } catch (e) {
            // Try decoding once if it was already URL-encoded upstream
            try {
                return JSON.parse(decodeURIComponent(attributesJson));
            } catch (decodeError) {
                return null;
            }
        }
    }

    // Helper function to ensure hidden fields are set before submission
    function ensureHiddenFieldsSet() {
        ensureGiftFieldsExist();

        if (selectionType === 'single') {
            // Find the selected gift
            for (let productId in selectedGifts) {
                const giftData = selectedGifts[productId];
                let variationId = 0;
                let attributes = {};

                if (typeof giftData === 'object' && giftData !== null) {
                    variationId = giftData.variation_id || 0;
                    attributes = giftData.attributes || {};
                } else {
                    variationId = giftData || 0;
                }

                $('#mmd_selected_gift_product').val(productId);
                $('#mmd_selected_gift_variation').val(variationId || '');
                if (Object.keys(attributes).length > 0) {
                    $('#mmd_selected_gift_attributes').val(JSON.stringify(attributes));
                }

                // Also store in global for AJAX compatibility
                window.mmdGiftSelectionData = {
                    product: productId,
                    variation: variationId || '',
                    attributes: Object.keys(attributes).length > 0 ? JSON.stringify(attributes) : ''
                };

                // Get the stored attributes from the hidden field and add them as individual fields
                const attributesJson = $('#mmd_selected_gift_attributes').val();
                if (attributesJson) {
                    try {
                        const attributes = parseGiftAttributes(attributesJson);

                        if (attributes) {
                            // Remove any existing gift attribute fields to avoid duplicates
                            originalAddToCartForm.find('input[name^="gift_attribute_"]').remove();

                            // Add individual hidden fields for each attribute
                            // Use gift_attribute_ prefix to avoid conflicts with main product attributes
                            for (let attrName in attributes) {
                                const attrValue = attributes[attrName];
                                const hiddenField = $('<input>')
                                    .attr('type', 'hidden')
                                    .attr('name', 'gift_' + attrName)
                                    .val(attrValue);
                                originalAddToCartForm.append(hiddenField);
                                console.log('Added gift attribute field:', 'gift_' + attrName, '=', attrValue);
                            }
                        } else {
                            console.error('Error parsing gift attributes');
                        }
                    } catch (e) {
                        console.error('Error parsing gift attributes:', e);
                    }
                }

                console.log('Ensured hidden fields set: product=' + productId + ', variation=' + variationId);
                break; // Only one selection in single mode
            }
        } else {
            // Multiple mode - ensure container has all the data
            updateHiddenFields();
        }
    }

    // Chained add-to-cart: Add gift product after main product is added
    // This ensures both products make it to the cart, especially for variations

    // Track if gift is being added to prevent duplicates
    let giftAdditionInProgress = false;

    // Handle the chained gift add-to-cart
    function handleChainedGiftAddition() {
        console.log('MMD Gift: Main product added to cart, checking for gift selection...');

        // Prevent duplicate calls
        if (giftAdditionInProgress) {
            console.log('MMD Gift: Gift addition already in progress, skipping duplicate call');
            return;
        }

        const giftContainer = $('.mmd-gift-selection-container');
        if (!giftContainer.length) {
            console.log('MMD Gift: No gift container found, skipping');
            return;
        }

        const hasSelection = Object.keys(selectedGifts).length > 0;
        if (!hasSelection) {
            console.log('MMD Gift: No gift selected, skipping');
            return;
        }

        // Get the parent product ID
        const parentProductId = giftContainer.data('parent-product-id');
        if (!parentProductId) {
            console.error('MMD Gift: Parent product ID not found');
            return;
        }

        // Mark as in progress
        giftAdditionInProgress = true;

        console.log('MMD Gift: Gift selected, triggering chained add-to-cart...');
        console.log('MMD Gift: Selection type:', selectionType);

        // Handle based on selection type
        if (selectionType === 'single') {
            // Single gift selection
            const giftProductId = $('#mmd_selected_gift_product').val();
            const giftVariationId = $('#mmd_selected_gift_variation').val();
            const giftAttributesJson = $('#mmd_selected_gift_attributes').val();

            if (!giftProductId) {
                console.error('MMD Gift: No gift product ID found');
                giftAdditionInProgress = false;
                return;
            }

            // Get quantity from selectedGifts if available
            let quantity = 1;
            if (selectedGifts[giftProductId] && typeof selectedGifts[giftProductId] === 'object') {
                quantity = selectedGifts[giftProductId].quantity || 1;
            }

            addGiftToCart(parentProductId, giftProductId, giftVariationId, giftAttributesJson, quantity);
        } else {
            // Multiple gift selection - chain AJAX calls sequentially
            console.log('MMD Gift: Adding multiple gifts:', selectedGifts);

            // Build array of gifts to add
            const giftsToAdd = [];
            for (let selectionKey in selectedGifts) {
                const giftData = selectedGifts[selectionKey];

                // Handle new format (with product_id in the object)
                let productId, variationId, attributes, quantity;
                if (typeof giftData === 'object' && giftData.product_id) {
                    // New variation-specific format
                    productId = giftData.product_id;
                    variationId = giftData.variation_id || 0;
                    attributes = giftData.attributes || {};
                    quantity = giftData.quantity || 1;
                } else {
                    // Old format (backward compatibility)
                    productId = selectionKey;
                    variationId = typeof giftData === 'object' ? giftData.variation_id : giftData;
                    attributes = typeof giftData === 'object' ? giftData.attributes : {};
                    quantity = typeof giftData === 'object' && giftData.quantity ? giftData.quantity : 1;
                }

                giftsToAdd.push({
                    productId: productId,
                    variationId: variationId,
                    attributes: attributes,
                    quantity: quantity
                });
            }

            // Chain the AJAX calls sequentially
            addMultipleGiftsSequentially(parentProductId, giftsToAdd, 0);
        }
    }

    // Hook into standard WooCommerce event
    // The server-side handle_gift_selection now handles AJAX too,
    // but keep this as a fallback for themes with non-standard AJAX flows
    $(document).on('added_to_cart', function(e, fragments, cart_hash, $button) {
        // Only fire chained addition if gift data wasn't already in the form POST
        // (server-side handler would have processed it)
        var $form = $('form.cart');
        var giftFieldVal = $form.length ? $form.find('#mmd_selected_gift_product').val() : '';
        if (giftFieldVal && giftFieldVal.length > 0) {
            // Form had gift data with actual values - server already handled the gift addition
            // Clear selection state and field to prevent any future double-add
            selectedGifts = {};
            giftAdditionInProgress = false;
            $form.find('#mmd_selected_gift_product').val('');
            $form.find('#mmd_selected_gift_variation').val('');
            $form.find('#mmd_selected_gift_attributes').val('');
            return;
        }
        // No gift data in form fields - use JS AJAX fallback
        handleChainedGiftAddition();
    });

    // Hook into WoodMart-specific event
    $(document).on('wdShopPageUpdated', function() {
        console.log('MMD Gift: WoodMart shop page updated event detected');
        handleChainedGiftAddition();
    });

    // Alternative: Listen for AJAX success on WoodMart add to cart
    $(document).ajaxSuccess(function(event, xhr, settings) {
        // Check if this is a WoodMart add to cart request
        if (settings.data && typeof settings.data === 'string' &&
            settings.data.indexOf('woodmart_ajax_add_to_cart') > -1) {
            console.log('MMD Gift: Detected successful WoodMart AJAX add to cart');
            // Small delay to ensure cart is updated
            setTimeout(handleChainedGiftAddition, 300);
        }
    });

    // Helper function to add multiple gifts sequentially (one after another)
    function addMultipleGiftsSequentially(parentProductId, giftsToAdd, currentIndex) {
        // Base case: all gifts added
        if (currentIndex >= giftsToAdd.length) {
            console.log('MMD Gift: All gifts added successfully!');
            giftAdditionInProgress = false;
            // Clear selection to prevent double-add
            selectedGifts = {};
            // Trigger final cart update with forced refresh
            $.ajax({
                url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                type: 'POST',
                success: function(data) {
                    if (data && data.fragments) {
                        $.each(data.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                        $(document.body).trigger('wc_fragments_refreshed');
                    }
                }
            });
            $(document.body).trigger('wc_fragment_refresh');
            $(document.body).trigger('update_checkout');
            return;
        }

        // Get current gift to add
        const gift = giftsToAdd[currentIndex];
        console.log('MMD Gift: Adding gift ' + (currentIndex + 1) + ' of ' + giftsToAdd.length);
        console.log('MMD Gift: Gift product ID:', gift.productId);

        const attributesJson = JSON.stringify(gift.attributes);

        // Add this gift, then recursively add the next one on success
        $.ajax({
            url: mmdGiftData.ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_add_gift_to_cart',
                nonce: mmdGiftData.nonce,
                parent_product_id: parentProductId,
                gift_product_id: gift.productId,
                gift_variation_id: gift.variationId || 0,
                gift_attributes: attributesJson,
                quantity: gift.quantity || 1
            },
            success: function(response) {
                console.log('MMD Gift: AJAX response for gift ' + (currentIndex + 1) + ':', response);

                if (response.success) {
                    console.log('MMD Gift: Successfully added gift ' + (currentIndex + 1) + ' to cart!');
                    // Add next gift (don't reset flag - let base case handle it)
                    addMultipleGiftsSequentially(parentProductId, giftsToAdd, currentIndex + 1);
                } else {
                    console.error('MMD Gift: Failed to add gift ' + (currentIndex + 1) + ' to cart:', response.data);
                    alert(( __( 'Error adding gift', 'incentiv' ) + ' ' ) + (currentIndex + 1) + ( ' ' + __( 'to cart:', 'incentiv' ) + ' ' ) + (response.data && response.data.message ? response.data.message : __( 'Unknown error', 'incentiv' )));
                    // Continue with next gift anyway (don't reset flag - let base case handle it)
                    addMultipleGiftsSequentially(parentProductId, giftsToAdd, currentIndex + 1);
                }
            },
            error: function(xhr, status, error) {
                console.error('MMD Gift: AJAX error for gift ' + (currentIndex + 1) + ':', error);
                console.error('MMD Gift: Status:', status);
                console.error('MMD Gift: Response:', xhr.responseText);

                alert(( __( 'Error adding gift', 'incentiv' ) + ' ' ) + (currentIndex + 1) + ( ' ' + __( 'to cart.', 'incentiv' ) ));
                // Continue with next gift anyway (don't reset flag - let base case handle it)
                addMultipleGiftsSequentially(parentProductId, giftsToAdd, currentIndex + 1);
            }
        });
    }

    // Helper function to add a single gift to cart via AJAX
    function addGiftToCart(parentProductId, giftProductId, giftVariationId, giftAttributesJson, quantity) {
        quantity = quantity || 1; // Default to 1 if not specified

        console.log('MMD Gift: Adding gift to cart via AJAX...');
        console.log('MMD Gift: Parent product ID:', parentProductId);
        console.log('MMD Gift: Gift product ID:', giftProductId);
        console.log('MMD Gift: Gift variation ID:', giftVariationId);
        console.log('MMD Gift: Gift attributes:', giftAttributesJson);
        console.log('MMD Gift: Quantity:', quantity);

        // Call our dedicated AJAX endpoint to add the gift
        // The server will find the parent cart item automatically
        $.ajax({
            url: mmdGiftData.ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_add_gift_to_cart',
                nonce: mmdGiftData.nonce,
                parent_product_id: parentProductId,
                gift_product_id: giftProductId,
                gift_variation_id: giftVariationId || 0,
                gift_attributes: giftAttributesJson || '',
                quantity: quantity
            },
            success: function(response) {
                console.log('MMD Gift: AJAX response:', response);
                giftAdditionInProgress = false; // Reset flag

                if (response.success) {
                    console.log('MMD Gift: Successfully added gift to cart!');
                    // Clear selection to prevent double-add
                    selectedGifts = {};
                    // Force immediate cart fragments refresh
                    $.ajax({
                        url: wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'get_refreshed_fragments'),
                        type: 'POST',
                        success: function(data) {
                            if (data && data.fragments) {
                                $.each(data.fragments, function(key, value) {
                                    $(key).replaceWith(value);
                                });
                                $(document.body).trigger('wc_fragments_refreshed');
                            }
                        }
                    });
                    $(document.body).trigger('wc_fragment_refresh');
                    $(document.body).trigger('update_checkout');
                } else {
                    console.error('MMD Gift: Failed to add gift to cart:', response.data);
                    alert(( __( 'Error adding the gift to the cart:', 'incentiv' ) + ' ' ) + (response.data && response.data.message ? response.data.message : __( 'Unknown error', 'incentiv' )));
                }
            },
            error: function(xhr, status, error) {
                console.error('MMD Gift: AJAX error:', error);
                console.error('MMD Gift: Status:', status);
                console.error('MMD Gift: Response:', xhr.responseText);
                giftAdditionInProgress = false; // Reset flag

                alert(__( 'Error adding the gift to the cart. Please try again or contact support.', 'incentiv' ));
            }
        });
    }

    // ========================================
    // Pagination functionality
    // ========================================
    if (container.data('use-pagination') === 1 || container.data('use-pagination') === '1') {
        const productsPerPage = parseInt(container.data('products-per-page')) || 8;
        const totalProducts = parseInt(container.data('total-products')) || 0;
        const totalPages = Math.ceil(totalProducts / productsPerPage);
        let currentPage = 1;

        function showPage(page) {
            currentPage = page;
            const startIndex = (page - 1) * productsPerPage;
            const endIndex = startIndex + productsPerPage;

            container.find('.mmd-gift-product-row').each(function() {
                const index = parseInt($(this).data('product-index'));
                if (index >= startIndex && index < endIndex) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });

            // Update pagination buttons and info
            container.find('.mmd-current-page').text(currentPage);
            container.find('.mmd-total-pages').text(totalPages);
            container.find('.mmd-pagination-prev').prop('disabled', currentPage <= 1);
            container.find('.mmd-pagination-next').prop('disabled', currentPage >= totalPages);
        }

        container.on('click', '.mmd-pagination-next', function() {
            if (currentPage < totalPages) {
                showPage(currentPage + 1);
                // Scroll to top of gift container
                $('html, body').animate({
                    scrollTop: container.offset().top - 50
                }, 300);
            }
        });

        container.on('click', '.mmd-pagination-prev', function() {
            if (currentPage > 1) {
                showPage(currentPage - 1);
                $('html, body').animate({
                    scrollTop: container.offset().top - 50
                }, 300);
            }
        });
    }

    // ===== Cart/Checkout Gift Selection Handler =====

    // Resolve variation ID from selected attributes
    function resolveVariationId($row) {
        var variationsData = $row.data('variations');
        if (!variationsData || !variationsData.length) return 0;

        var selectedAttrs = {};

        // Include pre-selected attributes from the gift rule (hidden from UI)
        var $btn = $row.find('.mmd-cart-gift-add-btn');
        var preSelected = $btn.data('pre-selected');
        if (preSelected && typeof preSelected === 'object') {
            for (var key in preSelected) {
                selectedAttrs['attribute_' + key] = preSelected[key];
            }
        }

        // Include user-selected attributes from dropdowns
        $row.find('.mmd-cart-gift-attr-select').each(function() {
            var attrName = $(this).data('attribute');
            var attrVal = $(this).val();
            if (attrName && attrVal) {
                selectedAttrs[attrName] = attrVal;
            }
        });

        // Find matching variation
        for (var i = 0; i < variationsData.length; i++) {
            var variation = variationsData[i];
            var match = true;

            for (var attrKey in selectedAttrs) {
                var varValue = variation.attributes[attrKey] || '';
                // Empty string in variation = "any", matches everything
                if (varValue !== '' && varValue !== selectedAttrs[attrKey]) {
                    match = false;
                    break;
                }
            }

            if (match) {
                return variation.id;
            }
        }

        return 0;
    }

    // Handle attribute change - filter dependent attributes and resolve variation
    $(document).on('change', '.mmd-cart-gift-attr-select', function() {
        var $select = $(this);
        var $row = $select.closest('.mmd-cart-gift-row');
        var $btn = $row.find('.mmd-cart-gift-add-btn');

        $select.css('border-color', '');

        // Try to resolve the variation
        var variationId = resolveVariationId($row);
        $btn.data('variation-id', variationId);

        // Filter other attribute options based on available variations
        var variationsData = $row.data('variations');
        if (!variationsData) return;

        var currentAttr = $select.data('attribute');
        var currentVal = $select.val();

        // For each other attribute select, filter options
        $row.find('.mmd-cart-gift-attr-select').each(function() {
            var $otherSelect = $(this);
            var otherAttr = $otherSelect.data('attribute');
            if (otherAttr === currentAttr) return;

            var currentOtherVal = $otherSelect.val();
            var availableValues = [];

            // Find which values of this attribute are available given current selections
            variationsData.forEach(function(v) {
                // Check if this variation matches all other selected attributes
                var matches = true;
                $row.find('.mmd-cart-gift-attr-select').each(function() {
                    var attr = $(this).data('attribute');
                    var val = $(this).val();
                    if (attr === otherAttr || !val) return;
                    var vVal = v.attributes[attr] || '';
                    if (vVal !== '' && vVal !== val) matches = false;
                });

                if (matches) {
                    var vVal = v.attributes[otherAttr] || '';
                    if (vVal === '') {
                        // "Any" - all values are available
                        $otherSelect.find('option').each(function() {
                            if ($(this).val()) availableValues.push($(this).val());
                        });
                    } else if (availableValues.indexOf(vVal) === -1) {
                        availableValues.push(vVal);
                    }
                }
            });

            // Disable unavailable options
            $otherSelect.find('option').each(function() {
                var val = $(this).val();
                if (!val) return; // Skip placeholder
                if (availableValues.indexOf(val) === -1) {
                    $(this).prop('disabled', true).css('color', '#ccc');
                } else {
                    $(this).prop('disabled', false).css('color', '');
                }
            });

            // If current value is no longer available, reset
            if (currentOtherVal && availableValues.indexOf(currentOtherVal) === -1) {
                $otherSelect.val('');
            }
        });
    });

    // Handle "Add to cart" buttons
    $(document).on('click', '.mmd-cart-gift-add-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $row = $btn.closest('.mmd-cart-gift-row');
        var $container = $btn.closest('.mmd-cart-gift-selection-container');

        var productId = $btn.data('product-id');
        var variationId = $btn.data('variation-id') || 0;
        var triggerProduct = $btn.data('trigger-product');
        var ruleId = $btn.data('rule-id') || '';
        var giftType = $btn.data('gift-type') || 'free';
        var giftDiscount = $btn.data('gift-discount') || 0;
        var isVariable = $row.data('variable') === 1 || $row.data('variable') === '1';

        // For variable products, always run the resolver. We must do this
        // even when there are NO visible attribute dropdowns (rule auto-fills
        // every attribute) — otherwise variationId stays 0 and the AJAX
        // hits the simple-product branch on the server, which fails with
        // "X attribute is required" because WC can't add a variable parent
        // without a variation.
        if (isVariable && !variationId) {
            var $attrSelects = $row.find('.mmd-cart-gift-attr-select');

            // If there ARE visible dropdowns, require all of them to be picked.
            if ($attrSelects.length) {
                var allSelected = true;
                $attrSelects.each(function() {
                    if (!$(this).val()) {
                        allSelected = false;
                        $(this).css('border-color', 'red');
                        setTimeout(function() { $attrSelects.css('border-color', ''); }, 2000);
                    }
                });
                if (!allSelected) return;
            }

            variationId = resolveVariationId($row);
            if (!variationId) {
                // Fallback: pick the first variation in the filtered JSON
                // payload — the rule's filter has already trimmed it to
                // valid options, so any of them satisfies the rule.
                var fallbackData = $row.data('variations');
                if (fallbackData && fallbackData.length && fallbackData[0].id) {
                    variationId = fallbackData[0].id;
                } else {
                    alert(__( 'No matching variation found. Try a different combination.', 'incentiv' ));
                    return;
                }
            }
        }

        // Legacy: single variation select
        var $varSelect = $row.find('.mmd-cart-gift-variation-select');
        if ($varSelect.length && !variationId) {
            variationId = parseInt($varSelect.val());
            if (!variationId) {
                $varSelect.css('border-color', 'red');
                setTimeout(function() { $varSelect.css('border-color', ''); }, 2000);
                return;
            }
        }

        var originalText = $btn.text();
        $btn.prop('disabled', true).text(__( 'Adding...', 'incentiv' ));

        // Assemble the full attribute payload: pre-selected (rule) + user-picked.
        // The server uses this to fill "any"-typed slots in the variation.
        var attrPayload = {};
        var preSelected = $btn.data('pre-selected');
        if (preSelected && typeof preSelected === 'object') {
            for (var psKey in preSelected) {
                attrPayload['attribute_' + psKey] = preSelected[psKey];
            }
        }
        $row.find('.mmd-cart-gift-attr-select').each(function() {
            var k = $(this).data('attribute');
            var v = $(this).val();
            if (k && v) attrPayload[k] = v;
        });

        $.ajax({
            url: mmdGiftData.ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_add_cart_gift',
                nonce: mmdGiftData.nonce,
                product_id: productId,
                variation_id: variationId,
                trigger_product_id: triggerProduct,
                rule_id: ruleId,
                gift_type: giftType,
                gift_discount: giftDiscount,
                attributes: attrPayload
            },
            success: function(response) {
                if (response.success) {
                    // Close modal if open
                    $('.mmd-cart-gift-modal-overlay').fadeOut(300);

                    $container.html(
                        '<div style="text-align: center; padding: 20px;">' +
                        '<span style="font-size: 2em;">✅</span><br>' +
                        '<strong>' + response.data.message + '</strong>' +
                        '</div>'
                    );

                    setTimeout(function() {
                        if ($('body').hasClass('woocommerce-cart')) {
                            $('[name="update_cart"]').prop('disabled', false).trigger('click');
                            setTimeout(function() { location.reload(); }, 1000);
                        } else if ($('body').hasClass('woocommerce-checkout')) {
                            $(document.body).trigger('update_checkout');
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            location.reload();
                        }
                    }, 800);
                } else {
                    var msg = (response.data && response.data.message) || __( 'Error adding the gift', 'incentiv' );
                    if (response.data && response.data.debug) {
                        // Surface the server's debug echo into the console so
                        // we can see exactly what payload WC rejected and which
                        // keys it was looking for.
                        try {
                            console.error('[MMD gift add failed]', msg);
                            console.error('[MMD gift add debug]', response.data.debug);
                            console.error('[MMD] sent_attributes:', response.data.debug.sent_attributes);
                            console.error('[MMD] client_attrs:', response.data.debug.client_attrs);
                            console.error('[MMD] wc_expected_keys:', response.data.debug.wc_expected_keys);
                            console.error('[MMD] wc_variation_attrs_helper:', response.data.debug.wc_variation_attrs_helper);
                        } catch (e) {}
                    }
                    alert(msg);
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(__( 'Connection error. Try again.', 'incentiv' ));
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // ===== "Switch gift" button: removes the currently-claimed gift so
    // the selector re-appears and the customer can pick a different one. =====
    $(document).on('click', '.mmd-switch-gift-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var ruleId = $btn.data('rule-id') || '';
        var originalText = $btn.text();
        $btn.prop('disabled', true).text(__( 'Removing...', 'incentiv' ));

        $.ajax({
            url: mmdGiftData.ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_switch_cart_gift',
                nonce: mmdGiftData.nonce,
                rule_id: ruleId
            },
            success: function(response) {
                if (response && response.success) {
                    // Reload so the cart fragments + gift selector re-render
                    // with the gift removed.
                    location.reload();
                } else {
                    alert((response && response.data && response.data.message) || __( 'Error', 'incentiv' ));
                    $btn.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(__( 'Connection error', 'incentiv' ));
                $btn.prop('disabled', false).text(originalText);
            }
        });
    });

    // ===== Modal handlers =====
    $(document).on('click', '.mmd-open-gift-modal-btn', function() {
        $(this).closest('.mmd-cart-gift-notice').next('.mmd-cart-gift-modal-overlay').css('display', 'flex').hide().fadeIn(300);
    });

    $(document).on('click', '.mmd-close-gift-modal', function() {
        $(this).closest('.mmd-cart-gift-modal-overlay').fadeOut(300);
    });

    $(document).on('click', '.mmd-cart-gift-modal-overlay', function(e) {
        if ($(e.target).hasClass('mmd-cart-gift-modal-overlay')) {
            $(this).fadeOut(300);
        }
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') {
            $('.mmd-cart-gift-modal-overlay:visible').fadeOut(300);
        }
    });
});
