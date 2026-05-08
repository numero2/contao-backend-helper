class LongWordHighlighter {

    constructor( options = {} ) {

        this.config = {
            minLength: options.minLength || 20,
            selectors: options.selectors || [],
            excludeSelectors: options.excludeSelectors || [],
            enableTinyMCE: options.tinyMCE !== false,
            enableInputs: options.enableInputs !== false,
            debounceDelay: options.debounceDelay || 300
        };

        this.state = {
            domHighlights: new Set(),
            inputOverlays: new Map(),
            tinyMCEInstances: new Map(),
            observers: new Set(),
            debounceTimers: new Map()
        };

        this.init();
    }

    init() {

        this._destroyed = false;
        this.initDOMHighlights();

        if( this.config.enableInputs ) {
            this.initInputHighlights();
        }
        if( this.config.enableTinyMCE ) {
            this.initTinyMCE();
        }

        this.initGlobalListeners();
    }

    isHidden( node ) {

        let el = node.nodeType === Node.TEXT_NODE ? node.parentElement : node;

        while( el && el !== document.body ) {

            const style = getComputedStyle(el);

            if( style.display === 'none' || style.visibility === 'hidden' || style.opacity === '0' ) {
                return true;
            }

            el = el.parentElement;
        }

        return false;
    }

    isExcluded( node ) {

        if( !this.config.excludeSelectors.length ) {
            return false;
        }

        return this.config.excludeSelectors.some( sel => node.parentElement?.closest( sel ) );
    }

    createOverlay( rect, offsetX = 0, offsetY = 0 ) {

        const el = document.createElement( 'div' );

        el.className = 'bh-lwh-overlay';
        el.style.left = `${rect.left + offsetX}px`;
        el.style.top = `${rect.top  + offsetY}px`;
        el.style.width = `${rect.width}px`;
        el.style.height = `${rect.height}px`;
        return el;
    }

    initDOMHighlights() {
        this.scanAndHighlight();
        this.observeDOM();
    }

    scanAndHighlight() {
        if( this._destroyed ) return;
        this.cleanupDOMHighlights();
        this.getContainers().forEach( container => this.highlightInContainer( container ) );
    }

    highlightInContainer( container ) {

        const walker = document.createTreeWalker( container, NodeFilter.SHOW_TEXT, {

            acceptNode: (node)=>{

                const tag = node.parentElement?.tagName;

                if( ['SCRIPT', 'STYLE', 'NOSCRIPT', 'IFRAME', 'INPUT', 'TEXTAREA'].includes( tag ) ) {
                    return NodeFilter.FILTER_REJECT;
                }

                if( this.isExcluded( node ) || this.isHidden( node ) ) {
                    return NodeFilter.FILTER_REJECT;
                }

                return NodeFilter.FILTER_ACCEPT;
            }
        });

        let node;
        while( ( node = walker.nextNode() ) ) {

            this.findLongWords(node.nodeValue).forEach(({ start, end })=>{

                try {

                    const range = document.createRange();
                    range.setStart( node, start );
                    range.setEnd( node, end );

                    Array.from( range.getClientRects() ).forEach( rect => {
                        const overlay = this.createOverlay( rect, window.scrollX, window.scrollY );
                        document.body.appendChild( overlay );
                        this.state.domHighlights.add( { overlay, range } );
                    });

                } catch( e ) {}
            });
        }
    }

    cleanupDOMHighlights() {
        this.state.domHighlights.forEach( ( { overlay } ) => overlay.remove() );
        this.state.domHighlights.clear();
    }

    updateDOMOverlayPositions() {

        if( this._destroyed ) return;
        this.state.domHighlights.forEach(({ overlay, range })=>{

            try {
                const rect = range.getClientRects()[0];
                if( rect ) {
                    overlay.style.left = `${rect.left + window.scrollX}px`;
                    overlay.style.top  = `${rect.top  + window.scrollY}px`;
                }
            } catch( e ) {
                overlay.remove();
            }
        } );
    }

    observeDOM() {
        this.getContainers().forEach( container=>{
            const obs = new MutationObserver( () => this.debounce( 'dom', ()=>this.scanAndHighlight() ) );
            obs.observe( container, { childList: true, subtree: true, characterData: true } );
            this.state.observers.add( obs );
        } );
    }

    initInputHighlights() {
        this.scanInputs();
        const obs = new MutationObserver( ()=>this.debounce( 'inputs', ()=>this.scanInputs() ) );
        obs.observe( document.body, { childList: true, subtree: true } );
        this.state.observers.add( obs );
    }

    scanInputs() {
        document.querySelectorAll( 'textarea, input[type="text"], input:not([type])' ).forEach( input=>{
            if( !this.state.inputOverlays.has( input ) && this.isInTargetContainer( input ) && !this.isExcluded( input ) && !this.isHidden( input ) ) {
                this.createInputOverlay( input );
            }
        } );
    }

    createInputOverlay( input ) {

        const mirror = document.createElement( 'div' );
        Object.assign( mirror.style, {
            position:   'fixed',
            visibility: 'hidden',
            whiteSpace: 'pre-wrap',
            wordWrap:   'break-word',
            overflow:   'hidden'
        } );
        document.body.appendChild( mirror );

        const overlays = new Set();

        const syncMirrorStyle = () => {
            const rect  = input.getBoundingClientRect();
            const style = getComputedStyle( input );
            Object.assign( mirror.style, {
                left:          `${rect.left}px`,
                top:           `${rect.top}px`,
                width:         `${rect.width}px`,
                height:        `${rect.height}px`,
                font:          style.font,
                padding:       style.padding,
                lineHeight:    style.lineHeight,
                letterSpacing: style.letterSpacing,
                boxSizing:     style.boxSizing
            } );
        };

        const redrawOverlays = () => {
            if( this._destroyed ) return;
            overlays.forEach( o => o.remove() );
            overlays.clear();
            syncMirrorStyle();
            mirror.innerHTML = this.escapeHtml( input.value.replace( /\[-\]/g, ' ' ) ).replace(
                /\S+/gu,
                m => ( m.match( /\p{L}/gu ) ?? [] ).length >= this.config.minLength ? `<mark>${m}</mark>` : m
            );
            mirror.scrollTop  = input.scrollTop;
            mirror.scrollLeft = input.scrollLeft;
            mirror.querySelectorAll( 'mark' ).forEach( mark => {
                const overlay = this.createOverlay( mark.getBoundingClientRect(), window.scrollX, window.scrollY );
                document.body.appendChild( overlay );
                overlays.add( overlay );
            } );
            mirror.innerHTML = '';
        };

        input.addEventListener( 'input', redrawOverlays );
        input.addEventListener( 'scroll', redrawOverlays );
        window.addEventListener( 'scroll', redrawOverlays, { passive: true } );
        window.addEventListener( 'resize', redrawOverlays );

        redrawOverlays();
        this.state.inputOverlays.set( input, { mirror, overlays, input, redrawOverlays } );
    }

    initTinyMCE() {
        if( typeof tinymce === 'undefined' ) {
            return;
        }

        let editors = [];
        if( Array.isArray( tinymce.editors ) ) {
            editors = tinymce.editors;
        } else if( typeof tinymce.get === 'function' ) {
            try {
                editors = tinymce.get() || [];
            } catch( e ) {}
        } else if( tinymce.EditorManager?.editors ) {
            editors = Object.values( tinymce.EditorManager.editors );
        }

        editors.forEach( editor => this.setupTinyMCEEditor( editor ) );

        const onAdd = ( e ) => {
            if( e?.editor ) {
                this.setupTinyMCEEditor( e.editor );
            }
        };

        if( tinymce.on ) {
            this._tinyMCEHost = tinymce;
            this._tinyMCEAddEditorHandler = onAdd;
            tinymce.on( 'AddEditor', onAdd );
        } else if( tinymce.EditorManager?.on ) {
            this._tinyMCEHost = tinymce.EditorManager;
            this._tinyMCEAddEditorHandler = onAdd;
            tinymce.EditorManager.on( 'AddEditor', onAdd );
        } else {
            this.startTinyMCEPolling();
        }
    }

    startTinyMCEPolling() {
        const known = new Set();
        this._pollingInterval = setInterval( () => {
            let editors = [];
            try {
                editors = tinymce.get() || [];
            } catch( e ) {}
            editors.forEach( editor => {
                if( editor?.id && !known.has( editor.id ) ) {
                    known.add( editor.id );
                    this.setupTinyMCEEditor( editor );
                }
            } );
        }, 2000 );
    }

    setupTinyMCEEditor( editor ) {
        if( this.state.tinyMCEInstances.has( editor.id ) ) {
            return;
        }
        const state = { editor, overlays: new Set() };
        this.state.tinyMCEInstances.set( editor.id, state );
        if( !editor.initialized ) {
            editor.on( 'init', () => this.initTinyMCEContent( editor, state ) );
        } else {
            this.initTinyMCEContent( editor, state );
        }
    }

    initTinyMCEContent( editor, state ) {
        const body = editor.getBody();
        if( !body ) {
            return;
        }
        const iframeDoc    = editor.getDoc();
        const iframe       = editor.iframeElement;
        const iframeWindow = iframeDoc.defaultView || iframeDoc.parentWindow;

        Object.assign( state, { body, iframe, iframeDoc, iframeWindow } );

        const highlight = () => this.highlightTinyMCEContent( editor, state );

        state.editorChangeHandler = () => {
            this.debounce( `tinymce-${editor.id}`, highlight );
        };
        editor.on( 'input keyup change SetContent LoadContent', state.editorChangeHandler );

        const scrollHandler = () => requestAnimationFrame( highlight );
        body.addEventListener( 'scroll', scrollHandler, { passive: true } );
        if( iframeWindow ) {
            iframeWindow.addEventListener( 'scroll', scrollHandler, { passive: true } );
        }
        iframeDoc.addEventListener( 'scroll', scrollHandler, { passive: true } );
        editor.on( 'ScrollContent', scrollHandler );
        state.scrollHandler = scrollHandler;

        let lastScroll = 0;
        state.scrollPollInterval = setInterval( () => {
            const cur = iframeWindow?.pageYOffset ?? body.scrollTop;
            if( cur !== lastScroll ) {
                lastScroll = cur;
                requestAnimationFrame( highlight );
            }
        }, 100 );

        const windowHandler = () => this.debounce( `tinymce-win-${editor.id}`, highlight, 100 );
        window.addEventListener( 'scroll', windowHandler, { passive: true } );
        window.addEventListener( 'resize', windowHandler );
        state.windowUpdateHandler = windowHandler;

        const obs = new MutationObserver( () => this.debounce( `tinymce-mut-${editor.id}`, highlight ) );
        obs.observe( body, { childList: true, subtree: true, characterData: true } );
        state.observer = obs;

        highlight();
    }

    highlightTinyMCEContent( editor, state ) {
        if( this._destroyed ) return;
        state.overlays.forEach( o => o.remove() );
        state.overlays.clear();

        const { body, iframe, iframeDoc } = state;
        if( !iframe || !body || !iframeDoc ) {
            return;
        }

        const iframeRect     = iframe.getBoundingClientRect();
        const viewportHeight = iframe.clientHeight;
        const viewportWidth  = iframe.clientWidth;

        const walker = iframeDoc.createTreeWalker( body, NodeFilter.SHOW_TEXT, {
            acceptNode: ( node ) => {
                const tag = node.parentElement?.tagName;
                if( ['SCRIPT', 'STYLE', 'NOSCRIPT'].includes( tag ) ) {
                    return NodeFilter.FILTER_REJECT;
                }
                if( this.isExcluded( node ) || this.isHidden( node ) ) {
                    return NodeFilter.FILTER_REJECT;
                }
                return NodeFilter.FILTER_ACCEPT;
            }
        } );

        let node;
        while( ( node = walker.nextNode() ) ) {
            this.findLongWords( node.nodeValue ).forEach( ( { start, end } ) => {
                try {
                    const range = iframeDoc.createRange();
                    range.setStart( node, start );
                    range.setEnd( node, end );
                    Array.from( range.getClientRects() ).forEach( rect => {
                        if( rect.bottom < 0 || rect.top > viewportHeight || rect.right < 0 || rect.left > viewportWidth ) {
                            return;
                        }
                        const overlay = this.createOverlay( rect, iframeRect.left + window.scrollX, iframeRect.top + window.scrollY );
                        document.body.appendChild( overlay );
                        state.overlays.add( overlay );
                    } );
                } catch( e ) {}
            } );
        }
    }

    findLongWords( text ) {
        const display = text.replace( /\[-\]/g, ' ' );
        const results = [];
        let match;
        const regex = /\S+/gu;
        while( ( match = regex.exec( display ) ) ) {
            const letterCount = ( match[0].match( /\p{L}/gu ) ?? [] ).length;
            if( letterCount >= this.config.minLength ) {
                const range = this.mapToOriginalText( text, match.index, match[0].length );
                if( range ) {
                    results.push( range );
                }
            }
        }
        return results;
    }

    mapToOriginalText( text, displayStart, displayLength ) {
        let i = 0, j = 0;
        while( j < displayStart && i < text.length ) {
            if( text.substr( i, 3 ) === '[-]' ) {
                i += 3;
            } else {
                i++;
                j++;
            }
        }
        const start = i;
        let chars = 0;
        while( chars < displayLength && i < text.length ) {
            if( text.substr( i, 3 ) === '[-]' ) {
                i += 3;
            } else {
                i++;
                chars++;
            }
        }
        return start < i ? { start, end: i } : null;
    }

    getContainers( root = document ) {
        return this.config.selectors.flatMap( sel => Array.from( root.querySelectorAll( sel ) ) );
    }

    isInTargetContainer( element ) {
        return this.config.selectors.some( sel => element.closest( sel ) );
    }

    debounce( key, callback, delay = this.config.debounceDelay ) {
        clearTimeout( this.state.debounceTimers.get( key ) );
        this.state.debounceTimers.set( key, setTimeout( () => {
            callback();
            this.state.debounceTimers.delete( key );
        }, delay ) );
    }

    escapeHtml( text ) {
        const d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    initGlobalListeners() {
        let ticking = false;
        this._scrollHandler = () => {
            if( !ticking ) {
                requestAnimationFrame(()=>{
                    this.updateDOMOverlayPositions();
                    ticking = false;
                });
                ticking = true;
            }
        };
        this._resizeHandler = () => {
            this.debounce('resize', ()=>this.scanAndHighlight());
        };
        window.addEventListener('scroll', this._scrollHandler, { passive: true });
        window.addEventListener('resize', this._resizeHandler);
    }

    destroy() {

        this._destroyed = true;
        this.cleanupDOMHighlights();
        if( this._scrollHandler ) {
            window.removeEventListener('scroll', this._scrollHandler);
        }
        if( this._resizeHandler ) {
            window.removeEventListener('resize', this._resizeHandler);
        }
        this.state.inputOverlays.forEach( ( { mirror, overlays, input, redrawOverlays } ) => {
            mirror.remove();
            overlays.forEach( o => o.remove() );
            if( redrawOverlays ) {
                input.removeEventListener('input', redrawOverlays);
                input.removeEventListener('scroll', redrawOverlays);
                window.removeEventListener('scroll', redrawOverlays);
                window.removeEventListener('resize', redrawOverlays);
            }
        } );
        this.state.inputOverlays.clear();
        this.state.tinyMCEInstances.forEach( state => {
            state.observer?.disconnect();
            if( state.scrollHandler ) {
                state.body?.removeEventListener( 'scroll', state.scrollHandler );
                state.iframeWindow?.removeEventListener( 'scroll', state.scrollHandler );
                state.iframeDoc?.removeEventListener( 'scroll', state.scrollHandler );
                state.editor.off( 'ScrollContent', state.scrollHandler );
            }
            if( state.editorChangeHandler ) {
                state.editor.off( 'input keyup change SetContent LoadContent', state.editorChangeHandler );
            }
            if( state.scrollPollInterval ) {
                clearInterval( state.scrollPollInterval );
            }
            if( state.windowUpdateHandler ) {
                window.removeEventListener( 'scroll', state.windowUpdateHandler );
                window.removeEventListener( 'resize', state.windowUpdateHandler );
            }
            state.overlays.forEach( o => o.remove() );
        } );
        this.state.tinyMCEInstances.clear();
        if( this._tinyMCEHost && this._tinyMCEAddEditorHandler ) {
            this._tinyMCEHost.off?.( 'AddEditor', this._tinyMCEAddEditorHandler );
        }
        if( this._pollingInterval ) {
            clearInterval( this._pollingInterval );
        }
        this.state.observers.forEach( obs => obs.disconnect() );
        this.state.observers.clear();
        this.state.debounceTimers.forEach( t => clearTimeout( t ) );
        this.state.debounceTimers.clear();

        document.querySelectorAll('.bh-lwh-overlay').forEach( el => el.remove() );
    }

    refresh() {
        this.scanAndHighlight();
        this.scanInputs();
        this.state.tinyMCEInstances.forEach( state => {
            if( state.editor && state.body ) {
                this.highlightTinyMCEContent( state.editor, state );
            }
        } );
    }
}

window.LongWordHighlighter = LongWordHighlighter;