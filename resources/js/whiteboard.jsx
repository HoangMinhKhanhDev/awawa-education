let deps = null;
let reactRoot = null;
let excalidrawApi = null;
let latestScene = null;
let dirtyFlag = false;
let detachThemeListener = null;

async function loadDeps() {
    if (deps !== null) {
        return deps;
    }

    const [reactModule, reactDomModule, excalidrawModule] = await Promise.all([
        import('react'),
        import('react-dom/client'),
        import('@excalidraw/excalidraw'),
        import('@excalidraw/excalidraw/index.css'),
    ]);

    deps = {
        React: reactModule.default ?? reactModule,
        createRoot: reactDomModule.default?.createRoot ?? reactDomModule.createRoot,
        excalidraw: excalidrawModule,
    };

    return deps;
}

function currentTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

/*
 * Excalidraw nhét theme vào appState rồi serialize cả vào file .excalidraw, nên
 * bản lưu sẽ "đóng băng" theme của người soạn và ghi đè lên theme của người
 * mở. Bỏ nó ở cả lúc nạp lẫi lúc lưu để theme luôn lấy từ giao diện.
 */
function withoutStoredTheme(appState) {
    if (appState === null || appState === undefined) {
        return appState;
    }

    const { theme, ...rest } = appState;

    return rest;
}

function sanitizedInitialData(initialData) {
    if (initialData === null || initialData === undefined) {
        return initialData;
    }

    if (Array.isArray(initialData)) {
        return initialData;
    }

    return { ...initialData, appState: withoutStoredTheme(initialData.appState) };
}

function download(url, filename) {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
}

function blobToDataUrl(blob) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.readAsDataURL(blob);
    });
}

function sceneParts() {
    if (excalidrawApi !== null) {
        return {
            elements: excalidrawApi.getSceneElements(),
            appState: excalidrawApi.getAppState(),
            files: excalidrawApi.getFiles(),
        };
    }

    return latestScene ?? { elements: [], appState: {}, files: {} };
}

const whiteboard = {
    async mount(container, options = {}) {
        const { React, createRoot, excalidraw } = await loadDeps();

        if (reactRoot !== null) {
            reactRoot.unmount();
            reactRoot = null;
        }

        this.stopFollowingTheme();

        excalidrawApi = null;
        dirtyFlag = false;
        latestScene = null;

        reactRoot = createRoot(container);

        const App = () =>
            React.createElement(excalidraw.Excalidraw, {
                initialData: sanitizedInitialData(options.initialData ?? {}),
                theme: currentTheme(),
                langCode: 'vi-VN',
                UIOptions: {
                    canvasActions: {
                        loadScene: false,
                    },
                },
                excalidrawAPI: (api) => {
                    excalidrawApi = api;
                    options.onApi?.(api);
                },
                onChange: (elements, appState, files) => {
                    latestScene = { elements, appState, files };
                    dirtyFlag = true;
                    options.onChange?.();
                },
            });

        reactRoot.render(React.createElement(App));

        // Theo dõi thay đổi theme để canvas không bị kẹt ở theme lúc mount.
        const onThemeChange = (event) => {
            const theme = event.detail?.resolved ?? currentTheme();

            excalidrawApi?.updateScene?.({ appState: { theme } });
        };

        document.addEventListener('awawa:theme', onThemeChange);
        detachThemeListener = () => document.removeEventListener('awawa:theme', onThemeChange);
    },

    stopFollowingTheme() {
        if (typeof detachThemeListener === 'function') {
            detachThemeListener();
            detachThemeListener = null;
        }
    },

    isDirty() {
        return dirtyFlag;
    },

    markClean() {
        dirtyFlag = false;
    },

    getSceneJson() {
        const { excalidraw } = deps ?? {};
        const { elements, appState, files } = sceneParts();
        const cleanAppState = withoutStoredTheme(appState);

        if (excalidraw?.serializeAsJSON) {
            return excalidraw.serializeAsJSON(elements, cleanAppState, files, 'local');
        }

        return JSON.stringify({ type: 'excalidraw', elements, appState: cleanAppState, files });
    },

    async exportPng(filename = 'so-do.png') {
        const { excalidraw } = await loadDeps();
        const { elements, appState, files } = sceneParts();

        const blob = await excalidraw.exportToBlob({
            elements,
            appState: { ...appState, exportBackground: true },
            files,
            mimeType: 'image/png',
            quality: 1,
            exportPadding: 24,
        });

        download(URL.createObjectURL(blob), filename);
    },

    async exportSvg(filename = 'so-do.svg') {
        const { excalidraw } = await loadDeps();
        const { elements, appState, files } = sceneParts();

        const svg = await excalidraw.exportToSvg({
            elements,
            appState: { ...appState, exportBackground: true },
            files,
            exportPadding: 24,
        });

        const xml = new XMLSerializer().serializeToString(svg);
        const blob = new Blob([xml], { type: 'image/svg+xml;charset=utf-8' });

        download(URL.createObjectURL(blob), filename);
    },

    async exportJson(filename = 'so-do.excalidraw') {
        const json = whiteboard.getSceneJson();
        const blob = new Blob([json], { type: 'application/json' });

        download(URL.createObjectURL(blob), filename);
    },

    async exportPdf(filename = 'so-do.pdf') {
        const { excalidraw } = await loadDeps();
        const { default: JsPDF } = await import('jspdf');
        const { elements, appState, files } = sceneParts();

        const blob = await excalidraw.exportToBlob({
            elements,
            appState: { ...appState, exportBackground: true },
            files,
            mimeType: 'image/png',
            quality: 1,
            exportPadding: 24,
        });

        const dataUrl = await blobToDataUrl(blob);

        const image = new Image();
        await new Promise((resolve) => {
            image.onload = resolve;
            image.src = dataUrl;
        });

        const orientation = image.width >= image.height ? 'landscape' : 'portrait';
        const pdf = new JsPDF({ orientation, unit: 'pt', format: [image.width, image.height] });

        pdf.addImage(dataUrl, 'PNG', 0, 0, image.width, image.height);
        pdf.save(filename);
    },

    setTheme(theme) {
        excalidrawApi?.updateScene?.({ appState: { theme } });
    },
};

window.AwawaWhiteboard = whiteboard;

export default whiteboard;
