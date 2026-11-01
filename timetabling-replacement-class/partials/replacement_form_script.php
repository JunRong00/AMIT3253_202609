<?php
// Shared by create.php and edit.php: greys out time slots that have already
// started today, and venues that are already in use for the chosen date +
// slot (via venue_availability.php). The server re-checks both on submit -
// this is a convenience, not the real authority.
$excludeReplacementId = $excludeReplacementId ?? 0;
?>
<script>
(function () {
    var dateInput = document.getElementById('replacement-date');
    var slotSelect = document.getElementById('slot-select');
    var venueSelect = document.getElementById('venue-select');
    var hint = document.getElementById('availability-hint');
    var today = '<?= date('Y-m-d') ?>';
    var nowMinutes = <?= (int)date('H') * 60 + (int)date('i') ?>;
    var excludeId = <?= (int)$excludeReplacementId ?>;

    function reset(select) {
        Array.prototype.forEach.call(select.options, function (opt) {
            if (opt.dataset.originalText) {
                opt.textContent = opt.dataset.originalText;
            }
            opt.disabled = false;
        });
    }

    function markDisabled(select, opt, label) {
        opt.dataset.originalText = opt.dataset.originalText || opt.textContent;
        opt.textContent = opt.dataset.originalText + ' (' + label + ')';
        opt.disabled = true;
        if (select.value === opt.value) {
            select.value = '';
        }
    }

    function refresh() {
        var date = dateInput.value;
        var day = date ? new Date(date + 'T00:00:00').getDay() : 1;
        reset(slotSelect);
        reset(venueSelect);
        hint.textContent = '';

        if (day === 0) {
            hint.textContent = 'Replacement classes can only be held from Monday to Saturday.';
            return;
        }
        if (date === today) {
            Array.prototype.forEach.call(slotSelect.options, function (opt) {
                var parts = opt.dataset.start.split(':');
                if (parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10) < nowMinutes) {
                    markDisabled(slotSelect, opt, 'Started');
                }
            });
        }
        if (!date || !slotSelect.value) { return; }

        fetch('venue_availability.php?date=' + encodeURIComponent(date) + '&time_slot_id=' + encodeURIComponent(slotSelect.value) + '&exclude_id=' + excludeId)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var busy = (data.busy_venue_ids || []).map(String);
                Array.prototype.forEach.call(venueSelect.options, function (opt) {
                    if (busy.indexOf(opt.value) !== -1) {
                        markDisabled(venueSelect, opt, 'In use');
                    }
                });
                hint.textContent = data.holiday
                    ? 'This date is a public holiday (' + data.holiday + ') - please choose another date.'
                    : 'Greyed-out venues are already in use at this time; greyed-out slots have already started today.';
            })
            .catch(function () { /* availability check is a convenience, not required to submit */ });
    }

    dateInput.addEventListener('change', refresh);
    slotSelect.addEventListener('change', refresh);
    refresh();

    // Original Class: the chosen course's regular classes on the original
    // date. Picking one also fills in the class type.
    var courseSelect = document.getElementById('course-select');
    var originalDate = document.getElementById('original-date');
    var originalEntry = document.getElementById('original-entry');
    var typeSelect = document.querySelector('select[name="class_type"]');

    function fillType() {
        var opt = originalEntry.options[originalEntry.selectedIndex];
        if (opt && opt.dataset.type && typeSelect) {
            typeSelect.value = opt.dataset.type;
        }
    }

    function loadOriginalClasses() {
        if (!courseSelect || !originalDate.value) { return; }
        var previous = originalEntry.value;
        fetch('original_classes.php?course_id=' + encodeURIComponent(courseSelect.value) + '&date=' + encodeURIComponent(originalDate.value))
            .then(function (res) { return res.json(); })
            .then(function (data) {
                originalEntry.innerHTML = '';
                if (!data.classes.length) {
                    var none = document.createElement('option');
                    none.value = '';
                    none.textContent = '-- This course has no class on that day --';
                    originalEntry.appendChild(none);
                    return;
                }
                data.classes.forEach(function (c) {
                    var opt = document.createElement('option');
                    opt.value = c.id;
                    opt.dataset.type = c.class_type;
                    opt.textContent = c.label;
                    opt.selected = String(c.id) === previous;
                    originalEntry.appendChild(opt);
                });
                fillType();
            })
            .catch(function () { /* the server re-checks the chosen class on submit */ });
    }

    if (courseSelect && courseSelect.tagName === 'SELECT') {
        courseSelect.addEventListener('change', loadOriginalClasses);
    }
    originalDate.addEventListener('change', loadOriginalClasses);
    originalEntry.addEventListener('change', fillType);
})();
</script>
