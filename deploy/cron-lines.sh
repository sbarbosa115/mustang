# shellcheck shell=bash
# The cron line this deployment needs, and a check of the account's crontab against it. Sourced by
# deploy/cpanel-update.sh; it needs $PHP_BIN, $BACKEND and the note/warn helpers from there.
#
# One line, every minute: the queue worker. Email, appointment reminders (queued with a delay until they are due) and
# the customer and vehicle spreadsheet imports all wait in the database queue until it runs. Each run works for 55 seconds and
# then exits, so a worker never outlives a deploy by more than a minute and a stuck one is replaced by the next.

mustang_worker_line() {
    printf '* * * * * %s %s/bin/console messenger:consume async mail --time-limit=55 --memory-limit=200M --env=prod --no-debug --quiet >/dev/null 2>&1\n' \
        "$PHP_BIN" "$BACKEND"
}

# Reads a crontab on stdin and says whether the worker line is there, with the line to add when it is not.
check_cron_lines() {
    local crontab
    crontab="$(cat)"
    if grep -F "$BACKEND/bin/console messenger:consume" <<<"$crontab" | grep -v '^[[:space:]]*#' | grep -q 'async mail'; then
        note "The queue worker's cron line is in place."
        if ! grep -F "$BACKEND/bin/console messenger:consume" <<<"$crontab" | grep -qF "$PHP_BIN "; then
            warn "The worker's cron line runs another PHP than $PHP_BIN: replace it with the line below."
            warn "    $(mustang_worker_line)"
        fi
    else
        warn "The queue worker's cron line is missing: no email is sent and no import runs until it is added."
        warn "Add it in cPanel › Cron Jobs (Common settings: once per minute), the command being everything after the five stars:"
        warn "    $(mustang_worker_line)"
    fi
}
