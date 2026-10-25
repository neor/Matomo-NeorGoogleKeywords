(function ($) {
    'use strict';

    var categorySelector = '[data-category-id="NeorGoogleKeywords_MenuGoogleSearch"]';

    function parseQueryParams(str) {
        var params = {};
        if (!str) {
            return params;
        }
        var clean = str.replace(/^[#/?&]+/, '');
        if (!clean) {
            return params;
        }
        var pairs = clean.split('&');
        for (var i = 0; i < pairs.length; i++) {
            if (!pairs[i]) {
                continue;
            }
            var parts = pairs[i].split('=');
            var key = decodeURIComponent(parts[0]);
            var val = parts.length > 1 ? decodeURIComponent(parts.slice(1).join('=')) : '';
            params[key] = val;
        }
        return params;
    }

    function resolveSiteId(targetParams) {
        if (targetParams && targetParams.idSite && /^[0-9]+$/.test(targetParams.idSite)) {
            return targetParams.idSite;
        }
        if (window.matomo && window.matomo.idSite) {
            return String(window.matomo.idSite);
        }
        if (window.piwik && window.piwik.idSite) {
            return String(window.piwik.idSite);
        }
        if (window.MatomoUrl && window.MatomoUrl.parsed && window.MatomoUrl.parsed.value && window.MatomoUrl.parsed.value.idSite) {
            return String(window.MatomoUrl.parsed.value.idSite);
        }
        var match;
        if (window.location.search) {
            match = window.location.search.match(/[?&]idSite=([0-9]+)/);
            if (match) {
                return match[1];
            }
        }
        if (window.location.hash) {
            match = window.location.hash.match(/[?&#]idSite=([0-9]+)/);
            if (match) {
                return match[1];
            }
        }
        var $select = $('.ngk-site-form select[name="idSite"], select[name="idSite"]');
        if ($select.length && $select.val()) {
            return String($select.val());
        }
        return null;
    }

    function resolvePeriodAndDate(targetParams) {
        var period = targetParams && targetParams.period ? targetParams.period : null;
        var date = targetParams && targetParams.date ? targetParams.date : null;

        var match;
        if (!period && window.location.search) {
            match = window.location.search.match(/[?&]period=([a-zA-Z0-9_-]+)/);
            if (match) {
                period = match[1];
            }
        }
        if (!date && window.location.search) {
            match = window.location.search.match(/[?&]date=([^&#]+)/);
            if (match) {
                date = decodeURIComponent(match[1]);
            }
        }

        if (!period || !date) {
            var fromMatch = window.location.search ? window.location.search.match(/[?&]from=([0-9]{4}-[0-9]{2}-[0-9]{2})/) : null;
            var toMatch = window.location.search ? window.location.search.match(/[?&]to=([0-9]{4}-[0-9]{2}-[0-9]{2})/) : null;
            if (fromMatch && toMatch) {
                if (!period) {
                    period = 'range';
                }
                if (!date) {
                    date = fromMatch[1] + ',' + toMatch[1];
                }
            }
        }

        if (!period && window.piwik && window.piwik.period) {
            period = window.piwik.period;
        }
        if (!date && window.piwik && window.piwik.currentDateString) {
            date = window.piwik.currentDateString;
        }

        if (!period) {
            period = (date && date.indexOf(',') !== -1) ? 'range' : 'day';
        }
        if (!date) {
            date = (period === 'range') ? 'yesterday,yesterday' : 'yesterday';
        }

        return { period: period, date: date };
    }

    function buildCoreHomeUrl(rawTarget) {
        var targetParams = parseQueryParams(rawTarget);
        var idSite = resolveSiteId(targetParams);
        var pd = resolvePeriodAndDate(targetParams);

        var serverParams = [
            'module=CoreHome',
            'action=index'
        ];
        if (idSite) {
            serverParams.push('idSite=' + encodeURIComponent(idSite));
        }
        if (pd.period) {
            serverParams.push('period=' + encodeURIComponent(pd.period));
        }
        if (pd.date) {
            serverParams.push('date=' + encodeURIComponent(pd.date));
        }

        var baseUrl = 'index.php?' + serverParams.join('&');
        var hash = rawTarget || '';
        if (hash) {
            if (hash.indexOf('#?') !== 0) {
                if (hash.indexOf('#') === 0) {
                    hash = '#?' + hash.substring(1).replace(/^\?/, '');
                } else {
                    hash = '#?' + hash.replace(/^\?/, '');
                }
            }
        }
        return baseUrl + hash;
    }

    function getTargetUrl() {
        var idSite = resolveSiteId();
        var url = 'index.php?module=NeorGoogleKeywords&action=index&category=NeorGoogleKeywords_MenuGoogleSearch&subcategory=NeorGoogleKeywords_Overview';
        if (idSite) {
            url += '&idSite=' + encodeURIComponent(idSite);
        }
        return url;
    }

    function navigate(event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.which === 2) {
            return;
        }
        var url = getTargetUrl();
        event.preventDefault();
        window.location.href = url;
    }

    function syncActiveCategory() {
        if (!$('#ngk-report').length && !$('#ngk-navigation').length) {
            return;
        }
        var $targetLi = $(categorySelector);
        if ($targetLi.length) {
            $('.reportingMenu .menuTab.active, #mobile-left-menu .menuTab.active')
                .not($targetLi)
                .removeClass('active');
            $targetLi.addClass('active');
        }
        if (window.CoreHome && window.CoreHome.ReportingMenuStore) {
            try {
                var store = window.CoreHome.ReportingMenuStore;
                if (typeof store.findSubcategory === 'function' && typeof store.enterSubcategory === 'function') {
                    var found = store.findSubcategory('NeorGoogleKeywords_MenuGoogleSearch', 'NeorGoogleKeywords_Overview');
                    if (found && found.category && found.subcategory) {
                        store.enterSubcategory(found.category, found.subcategory);
                    }
                }
            } catch (e) {}
        }
    }

    function updateMenuLinks() {
        var url = getTargetUrl();
        $(categorySelector).find('a').each(function () {
            var current = $(this).attr('href');
            if (!current || current === '' || current === '#' || current.indexOf('#?') === 0) {
                $(this).attr('href', url);
            }
        });
        syncActiveCategory();
    }

    function syncSiteSelectColor(select) {
        if (!select) {
            return;
        }
        var option = select.options && select.selectedIndex >= 0 ? select.options[select.selectedIndex] : null;
        var isConfigured = option && (option.classList.contains('is-configured') || (option.style && option.style.color.indexOf('var(--ngk-clicks') !== -1));
        if (isConfigured) {
            select.classList.add('is-configured');
            select.style.color = 'var(--ngk-clicks, #1967d2)';
        } else {
            select.classList.remove('is-configured');
            select.style.color = '';
        }
    }

    function updateTopControlsVisibility() {
        var isNgkActive = $('#ngk-report, #ngk-navigation').length > 0;
        var $topControls = $('.top_controls');
        if (!$topControls.length) {
            return;
        }
        if (isNgkActive) {
            $topControls.attr('data-ngk-hidden', 'true');
            $topControls.css('display', 'none');
        } else if ($topControls.attr('data-ngk-hidden') === 'true') {
            $topControls.removeAttr('data-ngk-hidden');
            $topControls.css('display', '');
        }
    }

    function onHashChange() {
        syncActiveCategory();
        updateTopControlsVisibility();
        if (window.location.search && window.location.search.indexOf('module=NeorGoogleKeywords') !== -1) {
            var hash = window.location.hash || '';
            if (hash.indexOf('category=') !== -1 && hash.indexOf('NeorGoogleKeywords_') === -1) {
                window.location.href = buildCoreHomeUrl(hash);
            }
        }
    }

    $(function () {
        updateMenuLinks();
        syncActiveCategory();
        updateTopControlsVisibility();
        $('.ngk-site-form select[name="idSite"]').each(function () {
            syncSiteSelectColor(this);
        });
        setTimeout(function () {
            syncActiveCategory();
            updateTopControlsVisibility();
        }, 50);
        setTimeout(function () {
            syncActiveCategory();
            updateTopControlsVisibility();
        }, 250);
        if (window.Matomo && typeof window.Matomo.on === 'function') {
            window.Matomo.on('matomoPageChange', function () {
                updateMenuLinks();
                syncActiveCategory();
                updateTopControlsVisibility();
                $('.ngk-site-form select[name="idSite"]').each(function () {
                    syncSiteSelectColor(this);
                });
            });
            window.Matomo.on('updateReportingMenu', function () {
                updateMenuLinks();
                syncActiveCategory();
                updateTopControlsVisibility();
                $('.ngk-site-form select[name="idSite"]').each(function () {
                    syncSiteSelectColor(this);
                });
            });
        }
        window.addEventListener('hashchange', onHashChange);
    });

    $(document).on('mouseenter focusin', categorySelector, updateMenuLinks);
    $(document).on('click', categorySelector + ' a', navigate);
    $(document).on('change', '.ngk-site-form select[name="idSite"]', function () {
        syncSiteSelectColor(this);
    });

    // When on NeorGoogleKeywords full-page view, redirect relative SPA hash links to CoreHome with required site and date context
    $(document).on('click', '.reportingMenu a, #mobile-left-menu a', function (event) {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.which === 2) {
            return;
        }
        if (window.location.search && window.location.search.indexOf('module=NeorGoogleKeywords') !== -1) {
            var href = $(this).attr('href');
            if (href && href.indexOf('#?') === 0 && href.indexOf('NeorGoogleKeywords_') === -1) {
                event.preventDefault();
                window.location.href = buildCoreHomeUrl(href);
            }
        }
    });
}(jQuery));
