jQuery(document).ready(function($) {
    var __ = wp.i18n.__;
    var _x = wp.i18n._x;

    // Color picker
    if ($.fn.wpColorPicker) {
        $('.mmd-color-picker').wpColorPicker();
    }

    // Selection method toggle
    $('#selection_method').on('change', function() {
        var method = $(this).val();

        $('.mmd-section').hide();

        if (method === 'category') {
            $('#category_section').show();
        } else if (method === 'product_ids') {
            $('#product_ids_section').show();
        } else if (method === 'attribute') {
            $('#attribute_section').show();
        }
    }).trigger('change');

    // Add discount rule
    $('#add_discount_rule').on('click', function() {
        var container = $('#discount_rules_container');
        var index = container.find('.mmd-discount-rule').length;

        var ruleHtml = '<div class="mmd-discount-rule">' +
            '<div class="mmd-rule-fields">' +
            ( '<div class="mmd-field"><label>' + __( 'Quantity', 'incentiv' ) + '</label>' ) +
            '<input type="number" name="mmd_settings[discount_rules][' + index + '][quantity]" value="" min="1" placeholder="3"></div>' +
            ( '<div class="mmd-field"><label>' + __( 'Discount', 'incentiv' ) + '</label>' ) +
            '<input type="number" name="mmd_settings[discount_rules][' + index + '][discount]" value="" min="0" step="0.01" placeholder="30"></div>' +
            ( '<div class="mmd-field"><label>' + __( 'Type', 'incentiv' ) + '</label>' ) +
            '<select name="mmd_settings[discount_rules][' + index + '][type]">' +
            ( '<option value="percentage">' + __( 'Percentage (%)', 'incentiv' ) + '</option>' ) +
            ( '<option value="fixed">' + __( 'Fixed amount (₪)', 'incentiv' ) + '</option></select></div>' ) +
            ( '<button type="button" class="button mmd-remove-rule" title="' + __( 'Remove', 'incentiv' ) + '">🗑️<span class="screen-reader-text">' + __( 'Remove', 'incentiv' ) + '</span></button>' ) +
            '</div></div>';

        container.append(ruleHtml);
    });

    // Remove discount rule
    $(document).on('click', '.mmd-remove-rule', function() {
        if (confirm(__( 'Are you sure you want to remove this rule?', 'incentiv' ))) {
            $(this).closest('.mmd-discount-rule').fadeOut(300, function() {
                $(this).remove();
            });
        }
    });

    // Form validation
    $('form').on('submit', function(e) {
        var hasError = false;
        var errorMessage = '';

        // Check if discount rules are valid
        $('.mmd-discount-rule').each(function() {
            var quantity = $(this).find('input[name*="[quantity]"]').val();
            var discount = $(this).find('input[name*="[discount]"]').val();

            if (quantity && !discount) {
                hasError = true;
                errorMessage = __( 'Every discount tier needs both a quantity and a discount amount.', 'incentiv' );
                return false;
            }
        });

        if (hasError) {
            e.preventDefault();
            alert(errorMessage);
            return false;
        }
    });

    // ==========================================
    // WIZARD FUNCTIONALITY
    // ==========================================

    // Real-field 4-step wizard (Incentiv design): the wizard collects actual
    // values and writes them into the form on finish.
    var wizardCurrentStep = 1;
    var wizardTotalSteps = 4;

    var wizardState = {
        type: 'tiered',           // tiered | gift | global | timed
        name: '',
        method: 'all',            // all | category | product_ids | attribute
        categories: [],           // term ids
        tiers: [ { qty: 3, disc: 30 }, { qty: 4, disc: 50 }, { qty: 5, disc: 100 } ]
    };

    var wizardTypes = [
        { id: 'tiered', icon: 'stairs',      color: '#3a5bff', title: __( 'Tiered quantity discount', 'incentiv' ), desc: __( 'The more customers buy, the bigger the discount. 3+ = 30%, 5+ = free.', 'incentiv' ) },
        { id: 'gift',   icon: 'redeem',      color: '#7c3aed', title: __( 'Buy and get a gift', 'incentiv' ),    desc: __( 'The cheapest item is free (100% off) from a set quantity.', 'incentiv' ) },
        { id: 'global', icon: 'percent',     color: '#17b26a', title: __( 'Global discount', 'incentiv' ),     desc: __( 'A fixed percentage off every matching item in the cart.', 'incentiv' ) },
        { id: 'timed',  icon: 'celebration', color: '#f79009', title: __( 'Limited-time sale', 'incentiv' ),  desc: __( 'A tiered discount with a schedule. Set the dates in the form.', 'incentiv' ) }
    ];

    function wizardCollectCategories() {
        // Read the real category checkboxes from the form
        var cats = [];
        $('input[name="included_categories[]"]').each(function() {
            cats.push({ id: $(this).val(), label: $(this).closest('label').text().trim() });
        });
        return cats;
    }

    function wizardTierRow(i, tier, showType) {
        return '<div class="mmd-wz-tier" data-i="' + i + '" style="display:grid;grid-template-columns:auto 1fr 1fr auto;gap:10px;align-items:center;background:#f8fafc;border:1px solid #eef1f6;border-radius:10px;padding:11px 13px;margin-bottom:9px;">' +
            '<span style="width:26px;height:26px;border-radius:7px;background:' + (i === 2 ? '#7c3aed' : '#3a5bff') + ';color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;">' + (i + 1) + '</span>' +
            ( '<div><div style="font-size:11px;color:#98a2b3;margin-bottom:2px;">' + __( 'Quantity ≥', 'incentiv' ) + '</div><input type="number" min="1" class="mmd-wz-qty" value="' ) + tier.qty + '" style="width:100%;padding:7px 9px;border:1px solid #d0d7e2;border-radius:7px;font-weight:700;"></div>' +
            ( '<div><div style="font-size:11px;color:#98a2b3;margin-bottom:2px;">' + __( 'Discount %', 'incentiv' ) + '</div><input type="number" min="0" max="100" class="mmd-wz-disc" value="' ) + tier.disc + '" style="width:100%;padding:7px 9px;border:1px solid #d0d7e2;border-radius:7px;font-weight:700;"></div>' +
            '<button type="button" class="mmd-wz-del" style="width:30px;height:30px;border-radius:7px;border:none;background:#feecea;color:#f04438;cursor:pointer;font-weight:700;">✕</button>' +
            '</div>';
    }

    function wizardStepHtml(step) {
        var h = '';
        if (step === 1) {
            h += ( '<h3>' + __( 'What kind of discount do you want to create?', 'incentiv' ) + '</h3><p>' + __( 'Choose the template that best fits your promotion.', 'incentiv' ) + '</p>' );
            h += '<div class="mmd-wizard-type-grid">';
            wizardTypes.forEach(function(t) {
                var active = wizardState.type === t.id ? ' active' : '';
                h += '<div class="mmd-wizard-type-card' + active + '" data-type="' + t.id + '">' +
                    '<span class="mmd-type-check"><span class="msr">check</span></span>' +
                    '<span class="msr" style="color:' + t.color + ';">' + t.icon + '</span>' +
                    '<div class="mmd-type-title">' + t.title + '</div>' +
                    '<div class="mmd-type-desc">' + t.desc + '</div></div>';
            });
            h += '</div>';
        } else if (step === 2) {
            h += ( '<h3>' + __( 'Rule name and product selection', 'incentiv' ) + '</h3><p>' + __( 'How will you recognise this rule, and which products does it apply to?', 'incentiv' ) + '</p>' );
            h += ( '<label class="mmd-form-label">' + __( 'Rule name', 'incentiv' ) + '</label>' ) +
                '<input type="text" id="mmd-wz-name" value="' + $('<i>').text(wizardState.name).html() + ( '" placeholder="' + __( 'e.g. Buy 3 coffees, the cheapest is free', 'incentiv' ) + '" style="width:100%;padding:10px 12px;border:1px solid #d0d7e2;border-radius:9px;font-size:14px;margin-bottom:16px;">' );
            h += ( '<label class="mmd-form-label">' + __( 'Product selection method', 'incentiv' ) + '</label>' );
            h += '<div class="mmd-select-cards" style="margin-bottom:14px;">';
            [ { id: 'all', icon: 'select_all', label: __( 'All products', 'incentiv' ) },
              { id: 'category', icon: 'category', label: __( 'Categories', 'incentiv' ) },
              { id: 'product_ids', icon: 'inventory_2', label: __( 'Products', 'incentiv' ) },
              { id: 'attribute', icon: 'tune', label: __( 'Attribute', 'incentiv' ) } ].forEach(function(m) {
                var active = wizardState.method === m.id ? ' active' : '';
                h += '<div class="mmd-select-card' + active + '" data-method="' + m.id + '"><span class="msr">' + m.icon + '</span><span class="mmd-select-card-label">' + m.label + '</span></div>';
            });
            h += '</div>';
            var cats = wizardCollectCategories();
            if (cats.length) {
                h += '<div id="mmd-wz-cats" style="' + (wizardState.method === 'category' ? '' : 'display:none;') + '">';
                h += ( '<label class="mmd-form-label">' + __( 'Select categories', 'incentiv' ) + '</label><div style="display:flex;flex-wrap:wrap;gap:7px;">' );
                cats.forEach(function(c) {
                    var on = wizardState.categories.indexOf(String(c.id)) !== -1 ? ' mmd-chip-active' : '';
                    h += '<span class="mmd-chip mmd-wz-cat' + on + '" data-id="' + c.id + '">' + c.label + '</span>';
                });
                h += '</div></div>';
            }
            if (wizardState.method === 'product_ids' || wizardState.method === 'attribute') {
                h += ( '<div class="mmd-wizard-tip">' + __( 'You will finish the exact product or attribute selection in the form after the wizard.', 'incentiv' ) + '</div>' );
            }
        } else if (step === 3) {
            if (wizardState.type === 'global') {
                h += ( '<h3>' + __( 'How big is the discount?', 'incentiv' ) + '</h3><p>' + __( 'The discount applies to every matching item in the cart.', 'incentiv' ) + '</p>' );
                h += wizardTierRow(0, wizardState.tiers[0] || { qty: 1, disc: 10 });
            } else {
                h += ( '<h3>' + __( 'Set the discount tiers', 'incentiv' ) + '</h3><p>' + __( 'How many units earn how much discount? The discount applies to the cheapest item. 100% = free.', 'incentiv' ) + '</p>' );
                h += '<div id="mmd-wz-tiers">';
                wizardState.tiers.forEach(function(t, i) { h += wizardTierRow(i, t); });
                h += '</div>';
                h += ( '<button type="button" id="mmd-wz-add-tier" class="mmd-btn mmd-btn-dashed"><span class="msr">add</span>' + _x( 'Add tier', 'rule', 'incentiv' ) + '</button>' );
            }
        } else if (step === 4) {
            var typeInfo = wizardTypes.filter(function(t) { return t.id === wizardState.type; })[0];
            var methodLabels = { all: __( 'All products', 'incentiv' ), category: __( 'Selected categories', 'incentiv' ), product_ids: __( 'Selected products', 'incentiv' ), attribute: __( 'By attribute', 'incentiv' ) };
            h += ( '<h3>' + __( 'Almost done: final review', 'incentiv' ) + '</h3><p>' + __( 'Check the details and click "Create rule" to fill in the form.', 'incentiv' ) + '</p>' );
            h += '<div style="border:1px solid #e3e8ef;border-radius:12px;overflow:hidden;">';
            h += ( '<div style="display:flex;justify-content:space-between;padding:13px 16px;border-bottom:1px solid #f4f6f9;"><span style="color:#667085;">' + __( 'Name', 'incentiv' ) + '</span><strong>' ) + ($('<i>').text(wizardState.name || __( '(no name)', 'incentiv' )).html()) + '</strong></div>';
            h += ( '<div style="display:flex;justify-content:space-between;padding:13px 16px;border-bottom:1px solid #f4f6f9;"><span style="color:#667085;">' + __( 'Type', 'incentiv' ) + '</span><strong>' ) + typeInfo.title + '</strong></div>';
            h += ( '<div style="display:flex;justify-content:space-between;padding:13px 16px;border-bottom:1px solid #f4f6f9;"><span style="color:#667085;">' + __( 'Applies to', 'incentiv' ) + '</span><strong>' ) + methodLabels[wizardState.method] + (wizardState.method === 'category' && wizardState.categories.length ? ' (' + wizardState.categories.length + ')' : '') + '</strong></div>';
            h += ( '<div style="display:flex;justify-content:space-between;padding:13px 16px;"><span style="color:#667085;">' + __( 'Tiers', 'incentiv' ) + '</span><span style="display:flex;gap:5px;flex-wrap:wrap;">' );
            wizardState.tiers.forEach(function(t) {
                var free = parseFloat(t.disc) >= 100;
                h += '<span class="mmd-tier-chip' + (free ? ' mmd-tier-chip-purple' : '') + '">' + t.qty + '+ = ' + (free ? __( 'Free', 'incentiv' ) : t.disc + '%') + '</span>';
            });
            h += '</span></div></div>';
            h += ( '<div class="mmd-wizard-tip" style="background:#e9f9f0;border-color:#17b26a;color:#155724;margin-top:14px;">' + __( 'All set! Clicking "Create rule" fills in the form with your settings. There you can fine-tune messages, design and conditions, and then save.', 'incentiv' ) + '</div>' );
        }
        return '<div style="animation:mmdRise .25s ease both;">' + h + '</div>';
    }

    function wizardSyncStepUI() {
        $('.mmd-wizard-step').each(function() {
            var n = parseInt($(this).data('step'), 10);
            $(this).toggleClass('active', n === wizardCurrentStep).toggleClass('completed', n < wizardCurrentStep);
        });
        $('#mmd-wizard-prev').toggle(wizardCurrentStep > 1);
        $('#mmd-wizard-next').toggle(wizardCurrentStep < wizardTotalSteps);
        $('#mmd-wizard-finish').toggle(wizardCurrentStep === wizardTotalSteps);
        $('#mmd-wizard-title').text(__( 'Discount rule wizard', 'incentiv' ));
        $('#mmd-wizard-body').html(wizardStepHtml(wizardCurrentStep));
    }

    function wizardReadStepValues() {
        if (wizardCurrentStep === 2) {
            wizardState.name = $('#mmd-wz-name').val() || '';
        } else if (wizardCurrentStep === 3) {
            var tiers = [];
            $('#mmd-wizard-body .mmd-wz-tier').each(function() {
                var qty = parseInt($(this).find('.mmd-wz-qty').val(), 10);
                var disc = parseFloat($(this).find('.mmd-wz-disc').val());
                if (qty > 0 && !isNaN(disc)) tiers.push({ qty: qty, disc: disc });
            });
            if (tiers.length) {
                tiers.sort(function(a, b) { return a.qty - b.qty; });
                wizardState.tiers = tiers;
            }
        }
    }

    function showWizard() {
        wizardCurrentStep = 1;
        // Prefill name from form if present
        if (!wizardState.name) wizardState.name = $('#rule_name').val() || '';
        $('#mmd-wizard-overlay').fadeIn(150);
        wizardSyncStepUI();
    }

    function hideWizard() {
        $('#mmd-wizard-overlay').fadeOut(150);
    }

    // Apply the wizard state into the real form
    function wizardApplyToForm() {
        // Name
        if (wizardState.name) $('#rule_name').val(wizardState.name).trigger('input');

        // Mode
        var mode = wizardState.type === 'global' ? 'global' : 'quantity';
        $('#discount_mode').val(mode).trigger('change');

        // Selection method
        $('#selection_method').val(wizardState.method).trigger('change');

        // Categories
        if (wizardState.method === 'category') {
            $('input[name="included_categories[]"]').each(function() {
                $(this).prop('checked', wizardState.categories.indexOf(String($(this).val())) !== -1);
            });
        }

        // Gift preset: ensure top tier is 100%
        if (wizardState.type === 'gift' && wizardState.tiers.length) {
            wizardState.tiers[wizardState.tiers.length - 1].disc = 100;
        }

        // Rebuild tier rows in the form
        var $container = $('#discount_rules_container');
        if ($container.length) {
            $container.empty();
            wizardState.tiers.forEach(function(t, i) {
                var row = '<div class="mmd-discount-rule"><div class="mmd-rule-fields">' +
                    ( '<div class="mmd-field"><label>' + __( 'Quantity ≥', 'incentiv' ) + '</label><input type="number" name="discount_rules[' ) + i + '][quantity]" value="' + t.qty + '" min="1"></div>' +
                    ( '<div class="mmd-field"><label>' + __( 'Discount', 'incentiv' ) + '</label><input type="number" name="discount_rules[' ) + i + '][discount]" value="' + t.disc + '" min="0" step="0.01"></div>' +
                    ( '<div class="mmd-field"><label>' + __( 'Type', 'incentiv' ) + '</label><select name="discount_rules[' ) + i + ( '][type]"><option value="percentage" selected>' + __( 'Percentage %', 'incentiv' ) + '</option><option value="fixed">' + __( 'Fixed amount ₪', 'incentiv' ) + '</option></select></div>' ) +
                    '<button type="button" class="mmd-remove-rule">✕</button>' +
                    '</div></div>';
                $container.append(row);
            });
            $container.find('input').first().trigger('change');
        }

        // Land on the right step: timed promos go to conditions (schedule),
        // everything else goes to the summary step to review what was filled.
        if (typeof window.mmdShowEditorStep === 'function') {
            window.mmdShowEditorStep(wizardState.type === 'timed' ? 3 : 5);
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        if (wizardState.type === 'timed') {
            var $schedCard = $('.mmd-card').filter(function() { return $(this).find('.mmd-card-title .dashicons-calendar-alt').length > 0; }).first();
            if ($schedCard.length) {
                $schedCard.addClass('mmd-wizard-highlight');
                setTimeout(function() { $('html, body').animate({ scrollTop: $schedCard.offset().top - 120 }, 300); }, 250);
                setTimeout(function() { $schedCard.removeClass('mmd-wizard-highlight'); }, 3500);
            }
        }
    }

    // Auto-launch for new rules, on a full page load and after a PJAX swap
    // (the Wizard button on the rules list is a PJAX link).
    function maybeAutoLaunchWizard() {
        var urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('action') === 'new' && urlParams.get('skip_wizard') !== '1' && $('#mmd-wizard-overlay').length) {
            wizardState.name = '';
            showWizard();
        }
    }
    maybeAutoLaunchWizard();
    $(document).on('mmd:pjax:after', maybeAutoLaunchWizard);

    $(document).on('click', '#mmd-launch-wizard', function(e) { e.preventDefault(); showWizard(); });

    // Step interactions (delegated inside the wizard body)
    $(document).on('click', '.mmd-wizard-type-card', function() {
        wizardState.type = $(this).data('type');
        if (wizardState.type === 'global') {
            wizardState.tiers = [ { qty: 1, disc: 15 } ];
        } else if (wizardState.type === 'gift' && wizardState.tiers.length < 2) {
            wizardState.tiers = [ { qty: 2, disc: 0 }, { qty: 3, disc: 100 } ];
        }
        $('.mmd-wizard-type-card').removeClass('active');
        $(this).addClass('active');
    });

    $(document).on('click', '#mmd-wizard-body .mmd-select-card', function() {
        wizardState.method = $(this).data('method');
        $('#mmd-wizard-body .mmd-select-card').removeClass('active');
        $(this).addClass('active');
        $('#mmd-wz-cats').toggle(wizardState.method === 'category');
    });

    $(document).on('click', '.mmd-wz-cat', function() {
        var id = String($(this).data('id'));
        var idx = wizardState.categories.indexOf(id);
        if (idx === -1) wizardState.categories.push(id); else wizardState.categories.splice(idx, 1);
        $(this).toggleClass('mmd-chip-active');
    });

    $(document).on('click', '#mmd-wz-add-tier', function() {
        wizardReadStepValues();
        var last = wizardState.tiers[wizardState.tiers.length - 1] || { qty: 2, disc: 20 };
        wizardState.tiers.push({ qty: last.qty + 1, disc: Math.min(100, last.disc + 20) });
        $('#mmd-wizard-body').html(wizardStepHtml(3));
    });

    $(document).on('click', '.mmd-wz-del', function() {
        wizardReadStepValues();
        var i = $(this).closest('.mmd-wz-tier').index('#mmd-wizard-body .mmd-wz-tier');
        if (wizardState.tiers.length > 1) wizardState.tiers.splice(i, 1);
        $('#mmd-wizard-body').html(wizardStepHtml(3));
    });

    $(document).on('click', '#mmd-wizard-next', function() {
        wizardReadStepValues();
        if (wizardCurrentStep < wizardTotalSteps) {
            wizardCurrentStep++;
            wizardSyncStepUI();
        }
    });

    $(document).on('click', '#mmd-wizard-prev', function() {
        wizardReadStepValues();
        if (wizardCurrentStep > 1) {
            wizardCurrentStep--;
            wizardSyncStepUI();
        }
    });

    $(document).on('click', '#mmd-wizard-finish', function() {
        wizardReadStepValues();
        wizardApplyToForm();
        hideWizard();
    });

    $(document).on('click', '.mmd-wizard-close, .mmd-wizard-backdrop', function() { hideWizard(); });

    // Close wizard on Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#mmd-wizard-overlay').is(':visible')) {
            hideWizard();
        }
    });

    // ==========================================
    // EXAMPLE RULES IMPORT
    // ==========================================

    $(document).on('click', '.mmd-import-example', function() {
        var $btn = $(this);
        var example = $btn.data('example');
        var nonce = $btn.data('nonce');

        if ($btn.prop('disabled')) return;

        if (!confirm(( __( 'Import this example rule?', 'incentiv' ) + '\nImport this example rule?' ))) {
            return;
        }

        $btn.prop('disabled', true).text(__( 'Importing...', 'incentiv' ));

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mmd_import_example_rule',
                example: example,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $btn.addClass('mmd-imported').text(__( 'Imported!', 'incentiv' ));
                    // Reload after short delay to show updated rules list
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                } else {
                    $btn.prop('disabled', false).text(__( 'Error - Try again', 'incentiv' ));
                    alert(response.data.message || 'Import failed');
                }
            },
            error: function() {
                $btn.prop('disabled', false).text(__( 'Error - Try again', 'incentiv' ));
                alert('Network error. Please try again.');
            }
        });
    });

});
