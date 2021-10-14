# Change Log
The change log for the Yale Second Language external module.  
Pete Charpentier, CRI Web Tools LLC
criwebtools@gmail.com

## [1.0.0] - 2020-10-01
### Initial Release

## [1.0.1] - 2020-10-16

Resolved the issue of REDCap's failure to detect
simultaneous users, caused by AJAX calls being
misinterpreted as navigation off form. Form support
is now entirely client-side.

## [1.0.2] - 2021-07-05

Added code to prevent PHP 8 exceptions thrown
when count() is passed a null argument.

## [1.1.0] - 2021-10-14

Refactored to PHP 7.2 compatibility level.
Added new setting: primary language name. This
is displayed instead of "primary" on
the primary language button on the data
collection form.