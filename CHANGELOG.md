
# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).
 
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
