(()=>{

    window.ContaoBackendHelper = {
        lwhStorageKey: 'bhLwhActive'
    };

    const initLongWordHighlighter = () => {

        const button = document.getElementById('bhLwhToggle');

        if( !button ) {
            return;
        }

        const init = async () => {

            window.ContaoBackendHelper.lwhInstance?.destroy();
            window.ContaoBackendHelper.lwhInstance = null;

            if( window.ContaoBackendHelper.lwhActive ) {

                if( window.ContaoBackendHelper.isInitializing ) {
                    return;
                }

                window.ContaoBackendHelper.isInitializing = true;

                try {

                    if( typeof window.LongWordHighlighter === 'undefined' ) {

                        await new Promise((resolve, reject) => {
                            const script = document.createElement('script');
                            script.src = 'bundles/backendhelper/js/long-word-highlighter.js';
                            script.onload = resolve;
                            script.onerror = reject;
                            document.head.appendChild(script);
                        });
                    }

                    if( !window.ContaoBackendHelper.lwhActive ) {
                        return;
                    }

                    window.ContaoBackendHelper.lwhInstance = new window.LongWordHighlighter({
                        minLength: button.dataset.minLength,
                        selectors: [
                            '#main .content form input',
                            '#main .content form textarea',
                            '#main .tl_listing_container > ul',
                            '#main .tl_listing_container > table tbody',
                        ],
                        excludeSelectors: [
                            '.tl_content_header',
                            '.operations-menu',
                        ],
                        tinyMCE: true,
                        enableInputs: true
                    });

                } finally {
                    window.ContaoBackendHelper.isInitializing = false;
                }
            }
        };

        // Always sync lwhActive and button state from sessionStorage
        window.ContaoBackendHelper.lwhActive = !!sessionStorage.getItem(window.ContaoBackendHelper.lwhStorageKey);
        button.dataset.state = window.ContaoBackendHelper.lwhActive ? 'active' : '';

        if( button.dataset.lwhBound ) {
            if( window.ContaoBackendHelper.lwhActive ){
                init();
            } else {
                window.ContaoBackendHelper.lwhInstance?.destroy();
                window.ContaoBackendHelper.lwhInstance = null;
            }
            return;
        }

        button.dataset.lwhBound = '1';

        button.addEventListener('click', (e)=>{

            e.preventDefault();

            const isActive = button.dataset.state === 'active';
            button.dataset.state = isActive ? '' : 'active';
            window.ContaoBackendHelper.lwhActive = !isActive;

            if( window.ContaoBackendHelper.lwhActive ) {
                sessionStorage.setItem(window.ContaoBackendHelper.lwhStorageKey, '1');
            } else {
                sessionStorage.removeItem(window.ContaoBackendHelper.lwhStorageKey);
                button.blur();
            }

            init();
        });

        if( window.ContaoBackendHelper.lwhActive ) {
            init();
        }
    };

    document.addEventListener('DOMContentLoaded', initLongWordHighlighter);
    document.addEventListener('turbo:load', initLongWordHighlighter);

    document.addEventListener('turbo:render', ()=>{
        window.ContaoBackendHelper.lwhInstance?.refresh();
    });

    document.addEventListener('turbo:frame-load', (e)=>{
        if( e.target.querySelector('#bhLwhToggle') ) {
            initLongWordHighlighter();
        }
        window.ContaoBackendHelper.lwhInstance?.refresh();
    });

    document.addEventListener('turbo:before-cache', ()=>{

        if( !window.ContaoBackendHelper.lwhInstance ) {
            return;
        }

        window.ContaoBackendHelper.lwhInstance.destroy();
        window.ContaoBackendHelper.lwhInstance = null;
    });

})();
