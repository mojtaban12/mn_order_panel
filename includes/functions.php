<?php
/**
 * MN Order Panel - Helper Functions
 * توابع کمکی
 */

if (!function_exists('sanitize_text_field')) {
    /**
     * Sanitize text field
     */
    function sanitize_text_field($value) {
        return trim(strip_tags($value ?? ''));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    /**
     * Sanitize textarea
     */
    function sanitize_textarea_field($value) {
        return trim(strip_tags($value ?? ''));
    }
}

if (!function_exists('sanitize_email')) {
    /**
     * Sanitize email
     */
    function sanitize_email($value) {
        return filter_var(trim($value ?? ''), FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('number_format_i18n')) {
    /**
     * Format number with Persian digits
     */
    function number_format_i18n($number, $decimals = 0) {
        return number_format($number, $decimals);
    }
}