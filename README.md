
<!-- PROJECT LOGO -->
<br />
<p align="center">
  <a href="https://github.com/UNICEFECAR/parent-buddy-cms">
    <img src="logo.png" alt="Logo" width="80" height="80">
  </a>

  <h3 align="center">Parent Buddy - CMS</h3>

  <p align="center">
    CMS for creating Content for Parent Buddy Application
    <br />
    <a href="https://github.com/UNICEFECAR/parent-buddy-cms"><strong>Explore the docs</strong></a>
    <br />
    <br />
    ·
    <a href="https://github.com/UNICEFECAR/parent-buddy-cms/issues">Report Bug</a>
    ·
    <a href="https://github.com/UNICEFECAR/parent-buddy-cms/issues">Request Feature</a>
  </p>
</p>


<!-- TABLE OF CONTENTS -->
## Table of Contents

* [About the Project](#about-the-project)
  * [Built With](#built-with)
  * [Modules Used](#modules-used)
* [Getting Started](#getting-started)
  * [Prerequisites](#prerequisites)
  * [Recommended](#recommended)
  * [Installation](#installation)
* [Contact](#contact)


<!-- ABOUT THE PROJECT -->
## About The Project

CMS system based on Acquia Lightning Drupal 8 Distribution that is used to create Content for ParentBuddy Application

### Built With

* [Drupal 8 Lightning Distribution](https://www.drupal.org/project/lightning)

### Modules Used

Many third party Drupal modules were used. These are the most important, the full list can be seen by examining composer.json (in the root of the project)

* [Frequently Asked Questions](https://www.drupal.org/project/faq) module
* [Webform](https://www.drupal.org/project/webform) module


<!-- GETTING STARTED -->
## Getting Started

To get a local copy up and running follow these steps.

### Prerequisites

* An available command line interface
* PHP version 7.3 or higher
* [Composer](https://getcomposer.org/) for managing installation and dependencies

### Recommended

* [Drush](https://www.drush.org/) for making development life easier


### Installation

1. Clone the repo
```sh
git clone https://github.com/UNICEFECAR/parent-buddy-cms.git
OR
git clone git@github.com:UNICEFECAR/parent-buddy-cms.git
```
2. Go into root of the project and run following commands
```sh
composer install

mkdir docroot/sites/default/files
chmod a+w docroot/sites/default/files
```
```
edit docroot/sites/default/default.settings.php by adding at the end of the file
# Sync Directory Path
$settings['config_sync_directory'] = '../config/sync';
```
```sh
cp docroot/sites/default/default.settings.php docroot/sites/default/settings.php
chmod a+w docroot/sites/default/settings.php
```
3. Create database that will be used with CMS system for example:
```sh
mysql -u username -p
CREATE DATABASE `database_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
exit;
```
4. System is now prepared for installation so go with your browser to `http://domain/docroot/` to run the installation
5. Select English as default site language
6. When prompted to select installation profile choose 'Use existing configuration'
7. Follow the rest of the installation process and finish installation of CMS
8. Once installation is finished - set `trusted_host_patterns` setting in settings.php to reflect your domain you will be running CMS from
9. Go back to command line and execute following commands to secure the site
```sh
chmod go-w docroot/sites/default
chmod go-w docroot/sites/default/settings.php
```
10. Go back to CMS and in Appearance section set 'Seven' as default and administration theme and disable 'Claro' theme
11. Create `administer_users` user and give him `Administer Users` role
12. Create `access_content` user and give him `Access Content` role


<!-- CONTACT -->
## Contact

Admin - admin@parentbuddyapp.org

Project Link: [https://github.com/UNICEFECAR/parent-buddy-cms](https://github.com/UNICEFECAR/parent-buddy-cms)
