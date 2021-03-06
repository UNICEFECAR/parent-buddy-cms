
# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [1.1.5] - 2021-06-03

### Changed
- Adjusted functionality for copying Content between languages to work with all available languages.


## [1.1.4] - 2021-04-25

### Changed
- Added Related Activities and Related Milestones fields to Milestone and Activity Content Types respectively.
- Added 3 new languages:
  - Belarus-Belarusian
  - Belarus-Russian
  - Serbia–English
- Adjusted API, so it can work with new fields.


## [1.1.3] - 2021-03-14

### Changed
- Added functionality that will copy Content between languages and have it ready for Translation review.


## [1.1.2] - 2021-02-23

### Changed
- Code that will adjust the editorial workflow and set that every stage of process is considered a default revision.


## [1.1.1] - 2021-02-15

### Changed
- Fix for Translation process when 'Translation Manager' and 'Translation Reviewer' are managing translations.


## [1.1] - 2021-02-02

### Added
- Added Activity Content Type
- Added new languages to the system:
    - Albania-Albanian
    - Bulgaria-Bulgarian
    - Greece-Arab
    - Greece-Greek
    - Greece-Persian
    - Kosovo-Albanian
    - Kosovo-Serbian
    - Kyrgyzstan-Kyrgyz
    - Kyrgyzstan-Russian
    - Montenegro-Montenegrin
    - North Macedonia-Albanian
    - North Macedonia-Macedonian
    - Serbia-Serbian
    - Tajikistan-Russian
    - Tajikistan-Tajik
    - Uzbekistan-Russian
    - Uzbekistan-Uzbek
- Added translation automatization using tmgmt and memsource modules
- Added functionality that allows choosing of source language during translation review
- Adjusted tmgmt functionality, so when translation is accepted the newly translated Content is unpublished and has moderation state 'Review after translation' set
- Adjusted tmgmt functionality, so it shows Job Titles in chosen Source Language not in default Source Language
    - Change to the 'ContentEntitySource.php' file needed to be made in order for the Job Items title to be shown in chosen Source Language. After an update of the tmgmt module is performed please replace/adjust the 'ContentEntitySource.php' in 'docroot/modules/contrib/tmgmt/sources/content/src/Plugin/tmgmt/Source/' folder with the one in 'docroot/modules/custom/halo_beba_api/replace/' folder
