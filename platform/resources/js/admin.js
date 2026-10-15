/*
 * Woodex OS admin bundle.
 *
 * - Alpine is provided by Livewire 4. Do NOT import Alpine here (docs/07 D-11).
 * - Preline: import only the plugins the admin uses (each registers window.HS*), and re-initialise
 *   them after Livewire navigation and DOM morphs (D-12). Add a plugin here AND to PRELINE_PLUGINS
 *   when a screen starts using it (e.g. 'preline/plugins/overlay' → 'HSOverlay').
 * - Heavy libraries (SortableJS, ApexCharts) load only on screens that need them.
 */
import 'preline/plugins/dropdown';

const PRELINE_PLUGINS = ['HSDropdown'];

const initPreline = () => {
    PRELINE_PLUGINS.forEach((name) => window[name]?.autoInit?.());
};

/* ---------- Alpine stores: sidebar + colour theme (TailAdmin behaviour) ---------- */
document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.store('sidebar', {
        expanded: true,
        mobileOpen: false,
        init() {
            // The desktop preference; below xl the sidebar is an off-canvas drawer (mobileOpen) at full width.
            this.expanded = localStorage.getItem('wx.sidebar') !== 'collapsed';
            this.applyWidth();
        },
        toggle() {
            if (window.innerWidth < 1280) {
                this.mobileOpen = !this.mobileOpen;
                return;
            }
            this.expanded = !this.expanded;
            localStorage.setItem('wx.sidebar', this.expanded ? 'expanded' : 'collapsed');
            this.applyWidth();
        },
        applyWidth() {
            // Drives --wx-sidebar-w (admin.css), which sizes the sidebar and offsets the content column.
            document.documentElement.dataset.sidebar = this.expanded ? 'expanded' : 'collapsed';
        },
        close() {
            this.mobileOpen = false;
        },
    });

    Alpine.store('theme', {
        dark: document.documentElement.classList.contains('dark'),
        toggle() {
            this.dark = !this.dark;
            document.documentElement.classList.toggle('dark', this.dark);
            localStorage.setItem('wx.theme', this.dark ? 'dark' : 'light');
        },
    });

    Alpine.store('toasts', {
        items: [],
        push(message, type = 'success') {
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            setTimeout(() => this.remove(id), 4500);
        },
        remove(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    });
});

/* ---------- Toasts dispatched from Livewire: $this->dispatch('toast', message: '…', type: 'success') ---------- */
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    window.Alpine?.store('toasts')?.push(detail?.message ?? 'Saved', detail?.type ?? 'success');
});

/* ---------- Pipeline board (SortableJS) ---------- */
let sortables = [];

async function initKanban() {
    sortables.forEach((sortable) => sortable.destroy());
    sortables = [];

    const columns = document.querySelectorAll('[data-kanban-column]');
    if (!columns.length) return;

    const { default: Sortable } = await import('sortablejs');

    columns.forEach((column) => {
        sortables.push(
            Sortable.create(column, {
                group: 'pipeline',
                animation: 150,
                draggable: '[data-lead-id]',
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd(event) {
                    const leadId = Number(event.item.dataset.leadId);
                    const toStage = event.to.dataset.kanbanColumn;
                    if (!leadId || !toStage || event.to === event.from) return;

                    const root = event.to.closest('[wire\\:id]');
                    const component = root ? window.Livewire.find(root.getAttribute('wire:id')) : null;
                    component?.call('moveLead', leadId, toStage);
                },
            }),
        );
    });
}

/* ---------- Charts (ApexCharts): <div data-chart='{"type":"area","series":[…],"categories":[…]}'> ---------- */
async function initCharts() {
    const elements = [...document.querySelectorAll('[data-chart]:not([data-chart-ready])')];
    if (!elements.length) return;

    // Claim the elements before the first await: DOMContentLoaded and livewire:navigated both call this on the
    // first page load, and claiming after the import rendered every chart twice.
    elements.forEach((element) => (element.dataset.chartReady = '1'));

    const { default: ApexCharts } = await import('apexcharts');
    const dark = document.documentElement.classList.contains('dark');

    elements.forEach((element) => {
        if (!element.isConnected) return;
        const config = JSON.parse(element.dataset.chart || '{}');

        // Small counts (e.g. 0-3 leads a day) get whole-number ticks instead of 0, 0.5, 1 ... rounded to duplicates.
        const values = (config.series ?? [])
            .flatMap((serie) => serie.data ?? [])
            .map((point) => Number(point !== null && typeof point === 'object' ? point.y : point))
            .filter(Number.isFinite);
        const max = Math.max(0, ...values);
        const yaxis = { min: 0, forceNiceScale: true, decimalsInFloat: 0, labels: { formatter: (value) => Math.round(value).toLocaleString() } };
        if (max <= 5) Object.assign(yaxis, { max: Math.max(1, max), tickAmount: Math.max(1, max) });

        const chart = new ApexCharts(element, {
            chart: {
                type: config.type ?? 'area',
                height: config.height ?? 260,
                fontFamily: 'Inter, sans-serif',
                toolbar: { show: false },
                zoom: { enabled: false },
                foreColor: dark ? '#98a2b3' : '#667085',
            },
            series: config.series ?? [],
            colors: config.colors ?? ['#344d75', '#a87442', '#12b76a'],
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
            dataLabels: { enabled: false },
            grid: { borderColor: dark ? '#1d2939' : '#e4e7ec', strokeDashArray: 4 },
            xaxis: { categories: config.categories ?? [], axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis,
            legend: { position: 'top', horizontalAlign: 'left' },
            tooltip: { theme: dark ? 'dark' : 'light' },
        });
        chart.render();
        fitChartToBox(chart, element);
    });
}

// ApexCharts ignores container resizes while its entry animation runs; re-fit whenever the box really changes
// (sidebar toggle, window resize, layout settling) and clean up once Livewire navigates the element away.
function fitChartToBox(chart, element) {
    let width = element.clientWidth;
    let timer;
    const observer = new ResizeObserver(() => {
        if (!element.isConnected) {
            observer.disconnect();
            chart.destroy();
            return;
        }
        if (!element.clientWidth || Math.abs(element.clientWidth - width) < 2) return;
        width = element.clientWidth;
        clearTimeout(timer);
        timer = setTimeout(() => chart.updateOptions({ chart: { width: '100%' } }, false, false), 120);
    });
    observer.observe(element);
}

const refreshWidgets = () => {
    initPreline();
    initKanban();
    initCharts();
};

document.addEventListener('DOMContentLoaded', () => {
    initKanban();
    initCharts();
});

document.addEventListener('livewire:navigated', refreshWidgets);

document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morphed', refreshWidgets);
});
