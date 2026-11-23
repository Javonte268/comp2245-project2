(() => {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // AJAX Ticket Filtering on index.php
    const filterForm = document.getElementById('ticket-filter-form');
    const listContainer = document.getElementById('ticket-list-container');

    if (filterForm && listContainer) {
        const fetchFilteredTickets = async () => {
            const params = new URLSearchParams(new FormData(filterForm));
            const res = await fetch(`api/tickets.php?${params.toString()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            const tickets = Array.isArray(data.tickets) ? data.tickets : [];

            if (tickets.length === 0) {
                listContainer.innerHTML = '<p class="empty-state">No tickets match the current filters.</p>';
                return;
            }

            const rowsHtml = tickets.map((t) => {
                const assignee = t.assigned_to ? escapeHtml(t.assigned_to) : 'Unassigned';
                const statusLabel = escapeHtml(String(t.status).replace(/_/g, ' '));
                return `
                    <tr>
                        <td>#${Number(t.id)}</td>
                        <td><a href="ticket.php?id=${Number(t.id)}">${escapeHtml(t.title)}</a></td>
                        <td><span class="badge status-${escapeHtml(t.status)}">${statusLabel}</span></td>
                        <td><span class="badge priority-${escapeHtml(t.priority)}">${escapeHtml(t.priority)}</span></td>
                        <td>${assignee}</td>
                        <td>${escapeHtml(t.created_at)}</td>
                    </tr>
                `;
            }).join('');

            listContainer.innerHTML = `
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Assignee</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </div>
            `;
        };

        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            fetchFilteredTickets();
        });

        ['filter-status', 'filter-priority'].forEach((id) => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('change', fetchFilteredTickets);
        });
    }

    // AJAX Quick Status / Assignee Update on ticket.php
    const quickForm = document.getElementById('quick-update-form');
    if (quickForm) {
        quickForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ticketId = quickForm.getAttribute('data-ticket-id');
            const feedback = document.getElementById('ticket-update-feedback');
            const formData = new FormData(quickForm);
            formData.append('ticket_id', ticketId || '');
            formData.append('csrf_token', csrfToken);

            const res = await fetch('api/ticket-update.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                if (feedback) feedback.textContent = data.error || 'Could not update ticket.';
                return;
            }

            const updated = data.ticket || {};
            const badge = document.getElementById('ticket-status-badge');
            const assigneeText = document.getElementById('ticket-assignee-text');
            if (badge && updated.status) {
                badge.className = `badge status-${updated.status}`;
                badge.textContent = String(updated.status).replace(/_/g, ' ');
            }
            if (assigneeText && updated.assignee_name) {
                assigneeText.textContent = updated.assignee_name;
            }
            if (feedback) {
                feedback.textContent = 'Ticket updated.';
            }
        });
    }

    // AJAX Add Note on ticket.php
    const noteForm = document.getElementById('add-note-form');
    if (noteForm) {
        noteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const ticketId = noteForm.getAttribute('data-ticket-id');
            const bodyInput = document.getElementById('note-body');
            const notesList = document.getElementById('notes-list');
            const feedback = document.getElementById('note-feedback');
            const bodyVal = bodyInput ? bodyInput.value.trim() : '';

            if (!bodyVal) return;

            const formData = new FormData();
            formData.append('ticket_id', ticketId || '');
            formData.append('body', bodyVal);
            formData.append('csrf_token', csrfToken);

            const res = await fetch('api/notes.php', {
                method: 'POST',
                body: formData
            });
            const data = await res.json().catch(() => ({}));

            if (!res.ok) {
                if (feedback) feedback.textContent = data.error || 'Failed to add note.';
                return;
            }

            const note = data.note;
            if (note && notesList) {
                const emptyMsg = document.getElementById('no-notes-msg');
                if (emptyMsg) emptyMsg.remove();

                const article = document.createElement('article');
                article.className = 'note-item';
                article.innerHTML = `
                    <div class="note-meta">
                        <strong>${escapeHtml(note.author)}</strong>
                        <span>${escapeHtml(note.created_at)}</span>
                    </div>
                    <p>${escapeHtml(note.body).replace(/\n/g, '<br>')}</p>
                `;
                notesList.appendChild(article);
            }

            if (bodyInput) bodyInput.value = '';
            if (feedback) feedback.textContent = 'Note posted.';
        });
    }
})();