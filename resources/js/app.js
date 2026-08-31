import './bootstrap';

const statusClass = (text) => {
    const normalized = text.trim().toLowerCase().replaceAll('_', ' ');

    if (['aktif', 'active', 'paid', 'posted', 'lunas', 'berhasil', 'success'].includes(normalized)) {
        return 'status-success';
    }

    if (['draft', 'partial', 'pending', 'sync error', 'stok rendah', 'belum dibayar'].includes(normalized)) {
        return 'status-warning';
    }

    if (['inactive', 'nonaktif', 'cancelled', 'canceled', 'batal', 'failed', 'gagal', 'reversed'].includes(normalized)) {
        return 'status-danger';
    }

    return 'status-muted';
};

const enhanceTables = () => {
    document.querySelectorAll('main table').forEach((table) => {
        if (!table.closest('.table-responsive')) {
            const currentParent = table.parentElement;
            const responsive = document.createElement('div');
            responsive.className = 'table-responsive';
            currentParent.insertBefore(responsive, table);
            responsive.appendChild(table);
        }

        const card = table.closest('.table-card') || table.closest('main > div, main section, main article');
        if (card && !card.classList.contains('table-card')) {
            card.classList.add('table-card');
        }
    });
};

const enhanceStatusBadges = () => {
    const statusWords = [
        'Aktif',
        'Nonaktif',
        'ACTIVE',
        'INACTIVE',
        'POSTED',
        'DRAFT',
        'CANCELLED',
        'PAID',
        'PARTIAL',
        'SYNC_ERROR',
        'REVERSED',
        'Pending',
        'Failed',
    ];

    document.querySelectorAll('main td, main dd, main p, main span').forEach((node) => {
        if (node.children.length > 0 || node.classList.contains('status-badge')) {
            return;
        }

        const value = node.textContent.trim();
        if (!statusWords.includes(value)) {
            return;
        }

        const badge = document.createElement('span');
        badge.className = `status-badge ${statusClass(value)}`;
        badge.textContent = value;
        node.textContent = '';
        node.appendChild(badge);
    });
};

const enhancePageHeaders = () => {
    document.querySelectorAll('main > div.mb-5.flex, main > div.mb-6:not(.grid)').forEach((header) => {
        if (!header.querySelector('p') || !header.querySelector('a, button')) {
            return;
        }

        header.classList.add('content-header');
        header.querySelector('p')?.classList.add('page-description');
    });
};

const enhanceEmptyStates = () => {
    document.querySelectorAll('main div[class*="border-dashed"]').forEach((empty) => {
        empty.classList.add('empty-state');
    });
};

document.addEventListener('DOMContentLoaded', () => {
    enhanceTables();
    enhanceStatusBadges();
    enhancePageHeaders();
    enhanceEmptyStates();
});
