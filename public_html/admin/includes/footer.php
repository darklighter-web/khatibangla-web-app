        </main>
    </div>
</div>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('-translate-x-full');
    overlay.classList.toggle('hidden');
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
    document.querySelectorAll('.relative .absolute').forEach(d => {
        if (!d.parentElement.contains(e.target)) d.classList.add('hidden');
    });
});

// Toast notification
function showToast(msg, type = 'success') {
    const toast = document.createElement('div');
    const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500', warning: 'bg-yellow-500' };
    toast.className = `fixed bottom-4 right-4 ${colors[type] || colors.info} text-white px-6 py-3 rounded-lg shadow-lg z-[60] transition-all transform translate-y-2 opacity-0`;
    toast.textContent = msg;
    document.body.appendChild(toast);
    requestAnimationFrame(() => { toast.classList.remove('translate-y-2', 'opacity-0'); });
    setTimeout(() => {
        toast.classList.add('translate-y-2', 'opacity-0');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Confirm delete
function confirmDelete(url, name) {
    if (confirm('Are you sure you want to delete "' + name + '"? This action cannot be undone.')) {
        window.location.href = url;
    }
}

// AJAX helper
async function apiCall(url, data = null, method = 'POST') {
    const opts = { method, headers: { 'Content-Type': 'application/json' } };
    if (data) opts.body = JSON.stringify(data);
    const res = await fetch(url, opts);
    return res.json();
}

// Format currency
function formatPrice(amount) {
    return '৳' + parseFloat(amount).toLocaleString('en-BD');
}
</script>
</body>
</html>
