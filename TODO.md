# TODO

## SHS Loading: Total hours missing
- [x] Identify cause: `api/attendance/shs-loading.php` does not return `total`, only `mon..sun`.
- [x] Fix UI: compute `total` in `attendance.html` for `currentTab === 'shs-loading'`.
- [ ] Verify manually: set SHS Loading values for Mon..Sun and ensure Total Hours column updates.

