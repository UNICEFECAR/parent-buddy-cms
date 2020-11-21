<?php

/**
 * @param $string
 *
 * @return null|string|string[]
 */
function halo_beba_api_clean_string($string) {
  return !empty($string) ? preg_replace('/[^\x20-\x7E]/','', $string) : NULL;
}
