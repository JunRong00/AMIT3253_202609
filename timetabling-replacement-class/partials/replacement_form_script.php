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
})();
</script>
