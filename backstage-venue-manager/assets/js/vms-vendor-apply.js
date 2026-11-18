(function () {
    function readVariantMap() {
        var configNode = document.getElementById('vms-vendor-apply-variant-map');
        if (!configNode) {
            return {};
        }

        try {
            var parsed = JSON.parse(configNode.textContent || '{}');
            if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
                return {};
            }
            return parsed;
        } catch (error) {
            return {};
        }
    }

    function readString(value, fallback) {
        return typeof value === 'string' && value !== '' ? value : fallback;
    }

    function readVisibleSocials(value) {
        if (!Array.isArray(value)) {
            return [];
        }

        return value.filter(function (slug) {
            return typeof slug === 'string' && slug !== '';
        });
    }

    function setGroupState(elements, visible) {
        elements.forEach(function (element) {
            element.hidden = !visible;
            element.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !visible;
                if (!visible) {
                    field.required = false;
                }
            });
        });
    }

    var sel = document.getElementById('vms-app-vendor-type');
    if (!sel) {
        return;
    }

    var variantMap = readVariantMap();
    var bandSections = Array.prototype.slice.call(document.querySelectorAll('.vms-app-band'));
    var concessionSections = Array.prototype.slice.call(document.querySelectorAll('.vms-app-concession'));
    var bandRequiredFields = Array.prototype.slice.call(document.querySelectorAll('[data-vms-band-required]'));
    var socialFields = Array.prototype.slice.call(document.querySelectorAll('.vms-app-social-field'));
    var socialGroup = document.getElementById('vms-app-social-group');
    var socialHeading = document.getElementById('vms-app-social-heading');
    var nameLabel = document.getElementById('vms-app-name-label');
    var websiteLabel = document.getElementById('vms-app-website-label');
    var concessionLabel = document.getElementById('vms-app-concession-label');
    var concessionInput = document.getElementById('vms-app-concession-input');
    var concessionMenuLabel = document.getElementById('vms-app-concession-menu-label');

    function toggle() {
        var value = sel.value;
        var config = variantMap[value];
        if (!config || typeof config !== 'object' || Array.isArray(config)) {
            config = variantMap.default;
        }
        if (!config || typeof config !== 'object' || Array.isArray(config)) {
            config = {};
        }

        var visibleSocials = readVisibleSocials(config.visible_socials);
        var socialCount = 0;

        if (nameLabel) {
            nameLabel.textContent = readString(config.name_label, 'Business / Vendor Name');
        }
        if (websiteLabel) {
            websiteLabel.textContent = readString(config.website_label, 'Website URL (optional)');
        }
        if (socialHeading) {
            socialHeading.textContent = readString(config.social_heading, 'Social links (optional)');
        }
        if (concessionLabel) {
            concessionLabel.textContent = readString(config.concession_label, 'Cuisine / Food Type');
        }
        if (concessionInput) {
            concessionInput.placeholder = readString(config.concession_placeholder, 'Tacos, BBQ, Burgers, Coffee, etc.');
        }
        if (concessionMenuLabel) {
            concessionMenuLabel.textContent = readString(config.concession_menu_label, 'Menu Link (optional)');
        }

        setGroupState(bandSections, value === 'band');
        bandRequiredFields.forEach(function (field) {
            field.required = value === 'band';
        });

        setGroupState(concessionSections, !!config.show_concession);

        socialFields.forEach(function (wrapper) {
            var slug = wrapper.getAttribute('data-vms-social-slug') || '';
            var visible = value !== '' && visibleSocials.indexOf(slug) !== -1;
            wrapper.hidden = !visible;
            wrapper.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !visible;
            });
            if (visible) {
                socialCount += 1;
            }
        });

        if (socialGroup) {
            socialGroup.hidden = !(value !== '' && socialCount > 0);
        }
    }

    sel.addEventListener('change', toggle);
    toggle();
})();
