<?php
// Old brand + fuel filter results page (POST "brand", "fueltype"): show them on the car listing.
require_once __DIR__ . '/includes/security.php';
include('includes/config.php');

$params = array_filter([
  'brand' => (int)($_POST['brand'] ?? $_GET['brand'] ?? 0) ?: '',
  'fueltype' => trim((string)($_POST['fueltype'] ?? $_GET['fueltype'] ?? '')),
], fn($value) => $value !== '');
redirect_to('car-listing' . ($params ? '?' . http_build_query($params) : ''));
