(function($) {
    'use strict';

    function escAttr(value) {
        return $('<div>').text(value || '').html();
    }

    function getField(name) {
        return $('[name="mfb_settings[' + name + ']"]');
    }

    function getVal(name, fallback) {
        const $field = getField(name);
        if (!$field.length) {
            return fallback || '';
        }
        const val = $field.val();
        return val === undefined || val === null || val === '' ? (fallback || '') : val;
    }

    function updatePreview($wrap, url) {
        const $preview = $wrap.find('.mfb-icon-preview');
        if (url) {
            $preview.removeClass('is-empty').html('<img src="' + escAttr(url) + '" alt="preview">');
        } else {
            $preview.addClass('is-empty').html('<span>' + ((window.mfb_admin_i18n && mfb_admin_i18n.defaultLabel) ? mfb_admin_i18n.defaultLabel : 'Default') + '</span>');
        }
        refreshLivePreview();
    }

    function refreshLivePreview() {
        const $card = $('.mfb-admin-preview-card');
        const $container = $('.mfb-preview-container');
        const $buttonsWrap = $('[data-preview-buttons]');
        if (!$card.length || !$container.length || !$buttonsWrap.length) {
            return;
        }

        const vars = {
            '--mfb-primary-color': getVal('primary_color', '#003F6E'),
            '--mfb-icon-color': getVal('icon_color', '#FFFFFF'),
            '--mfb-whatsapp-color': getVal('whatsapp_color', '#25D366'),
            '--mfb-call-color': getVal('call_color', '#1DDA50'),
            '--mfb-button-size-desktop': getVal('button_size_desktop', '58') + 'px',
            '--mfb-button-size-mobile': getVal('button_size_mobile', '58') + 'px',
            '--mfb-messenger-size-desktop': getVal('messenger_size_desktop', '58') + 'px',
            '--mfb-messenger-size-mobile': getVal('messenger_size_mobile', '52') + 'px',
            '--mfb-messenger-icon-scale': getVal('messenger_icon_scale', '82') + '%',
            '--mfb-button-radius': getVal('button_radius', '999') + 'px',
            '--mfb-messenger-button-radius': getVal('messenger_button_radius', '999') + 'px'
        };

        Object.keys(vars).forEach(function(key) {
            $card[0].style.setProperty(key, vars[key]);
        });

        const tooltip = $('[name="mfb_settings[tooltip_text][ru]"]').val() || $('[name="mfb_settings[tooltip_text][en]"]').val() || 'Написать в мессенджер';
        $container.find('.mfb-tooltip').text(tooltip);

        const mainIcon = getVal('main_icon', '');
        const $mainCustom = $container.find('.mfb-main-custom-icon');
        const $mainDefault = $container.find('.mfb-icon-comment');
        if (mainIcon) {
            $mainCustom.attr('src', mainIcon).removeClass('is-preview-hidden');
            $mainDefault.addClass('is-preview-hidden');
        } else {
            $mainCustom.attr('src', '').addClass('is-preview-hidden');
            $mainDefault.removeClass('is-preview-hidden');
        }

        const defaultIcons = (window.mfb_admin_i18n && mfb_admin_i18n.defaultIcons) ? mfb_admin_i18n.defaultIcons : {};
        const labels = (window.mfb_admin_i18n && mfb_admin_i18n.labels) ? mfb_admin_i18n.labels : {};
        const iconFields = {
            call: getVal('call_icon', defaultIcons.call || ''),
            max: getVal('max_icon', defaultIcons.max || ''),
            telegram: getVal('telegram_icon', defaultIcons.telegram || ''),
            whatsapp: getVal('whatsapp_icon', defaultIcons.whatsapp || '')
        };
        const enabledByLink = {
            call: !!getVal('call_phone', ''),
            max: !!getVal('max_link', ''),
            telegram: !!getVal('telegram_link', ''),
            whatsapp: !!getVal('whatsapp_link', '')
        };

        const selected = [];
        $('[data-mfb-order]').each(function() {
            const $input = $(this);
            const type = $input.val();
            if ($input.is(':checked') && selected.indexOf(type) === -1) {
                selected.push(type);
            }
        });

        $buttonsWrap.empty();
        selected.forEach(function(type) {
            if (!enabledByLink[type] || !iconFields[type]) {
                return;
            }
            const label = labels[type] || type;
            const $btn = $('<button type="button" class="mfb-btn" aria-label=""></button>');
            $btn.addClass('mfb-' + type).attr('aria-label', label).attr('data-preview-type', type);
            $btn.append('<img src="' + escAttr(iconFields[type]) + '" alt="' + escAttr(label) + '">');
            $buttonsWrap.append($btn);
        });
    }

    $(document).on('click', '.mfb-upload-button', function(e) {
        e.preventDefault();

        const $button = $(this);
        const $wrap = $button.closest('.mfb-image-upload');
        const $input = $wrap.find('.mfb-image-url');

        const frame = wp.media({
            title: mfb_admin_i18n.mediaTitle || 'Select or upload an icon',
            button: {
                text: mfb_admin_i18n.mediaButton || 'Use this icon'
            },
            multiple: false
        });

        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            if (attachment && attachment.url) {
                $input.val(attachment.url).trigger('change');
                updatePreview($wrap, attachment.url);
            }
        });

        frame.open();
    });

    $(document).on('click', '.mfb-clear-button', function(e) {
        e.preventDefault();

        const $wrap = $(this).closest('.mfb-image-upload');
        const $input = $wrap.find('.mfb-image-url');
        $input.val('').trigger('change');
        updatePreview($wrap, '');
    });

    $(document).on('input change', '.mfb-image-url', function() {
        const $input = $(this);
        updatePreview($input.closest('.mfb-image-upload'), $input.val());
    });

    $(document).on('input change', '#mfb-settings-form input, #mfb-settings-form select', function() {
        refreshLivePreview();
    });

    $(function() {
        refreshLivePreview();
    });
})(jQuery);
