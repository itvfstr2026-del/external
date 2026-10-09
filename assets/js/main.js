/**
 * Vignan's Question Paper Feedback Portal - JavaScript Components
 */

document.addEventListener('DOMContentLoaded', () => {
    initCharCounters();
    initModals();
    initCheckboxes();
    initSignatureCanvas();
});

// --------------------------------------------------------------------------- Toast Notifications
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <span>${message}</span>
        <button onclick="this.parentElement.remove()" style="background:none;border:none;color:#fff;cursor:pointer;font-size:1.1rem;">&times;</button>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(15px)';
            toast.style.transition = 'all 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }
    }, 4500);
}

// --------------------------------------------------------------------------- Live Character Counters
function initCharCounters() {
    const textareas = document.querySelectorAll('textarea[data-max-chars]');
    textareas.forEach(ta => {
        const max = parseInt(ta.getAttribute('data-max-chars'), 10);
        const counterId = ta.getAttribute('data-counter-id');
        const counter = counterId ? document.getElementById(counterId) : null;

        const update = () => {
            const current = ta.value.length;
            if (counter) {
                counter.textContent = `${current} / ${max} chars`;
                if (current > max * 0.9) {
                    counter.classList.add('near-limit');
                } else {
                    counter.classList.remove('near-limit');
                }
            }
        };

        ta.addEventListener('input', update);
        update();
    });
}

// --------------------------------------------------------------------------- Modals
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function initModals() {
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
}

// --------------------------------------------------------------------------- Table Checkboxes & Bulk Selection
function initCheckboxes() {
    const selectAll = document.querySelector('.select-all-checkbox');
    if (!selectAll) return;

    const rowBoxes = document.querySelectorAll('.row-checkbox');
    const updateSelectedCount = () => {
        const count = document.querySelectorAll('.row-checkbox:checked').length;
        const countEl = document.getElementById('selected-count-display');
        if (countEl) countEl.textContent = `${count} selected`;

        const bulkBtns = document.querySelectorAll('.bulk-action-btn');
        bulkBtns.forEach(btn => {
            btn.disabled = count === 0;
        });
    };

    selectAll.addEventListener('change', () => {
        rowBoxes.forEach(cb => {
            cb.checked = selectAll.checked;
        });
        updateSelectedCount();
    });

    rowBoxes.forEach(cb => {
        cb.addEventListener('change', () => {
            const allChecked = Array.from(rowBoxes).every(b => b.checked);
            selectAll.checked = allChecked;
            updateSelectedCount();
        });
    });

    updateSelectedCount();
}

// --------------------------------------------------------------------------- Signature Canvas Pad
function initSignatureCanvas() {
    const canvas = document.getElementById('sig-canvas');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let isDrawing = false;
    let hasDrawn = false;

    ctx.strokeStyle = "#000080"; // Navy ink
    ctx.lineWidth = 2.5;
    ctx.lineCap = "round";
    ctx.lineJoin = "round";

    const getPos = (e) => {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches && e.touches.length > 0 ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches && e.touches.length > 0 ? e.touches[0].clientY : e.clientY;
        const scaleX = canvas.width / (rect.width || canvas.width);
        const scaleY = canvas.height / (rect.height || canvas.height);
        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY
        };
    };

    const startDraw = (e) => {
        isDrawing = true;
        hasDrawn = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
    };

    const draw = (e) => {
        if (!isDrawing) return;
        if (e.cancelable) e.preventDefault();
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
    };

    const stopDraw = () => {
        if (isDrawing) {
            isDrawing = false;
            // Update hidden input with base64 data
            const input = document.getElementById('sig-canvas-data');
            if (input && hasDrawn) {
                input.value = canvas.toDataURL('image/png');
            }
        }
    };

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', stopDraw);

    canvas.addEventListener('touchstart', (e) => {
        if (e.cancelable) e.preventDefault();
        startDraw(e);
    }, { passive: false });

    canvas.addEventListener('touchmove', draw, { passive: false });
    window.addEventListener('touchend', stopDraw);

    const clearBtn = document.getElementById('sig-canvas-clear');
    if (clearBtn) {
        clearBtn.addEventListener('click', (e) => {
            e.preventDefault();
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasDrawn = false;
            const input = document.getElementById('sig-canvas-data');
            if (input) input.value = '';
        });
    }

    // Sync before form submission
    const parentForm = canvas.closest('form');
    if (parentForm) {
        parentForm.addEventListener('submit', () => {
            if (hasDrawn) {
                const input = document.getElementById('sig-canvas-data');
                if (input) {
                    input.value = canvas.toDataURL('image/png');
                }
            }
        });
    }
}
