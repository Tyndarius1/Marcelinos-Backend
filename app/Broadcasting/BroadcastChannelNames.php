<?php

namespace App\Broadcasting;

/**
 * Centralized broadcast channel name constants.
 * Single source of truth for channel names used by events and frontend.
 * Public channels (blocked-dates, rooms, etc.) are for frontend real-time updates only.
 */
final class BroadcastChannelNames
{
    /** Public: single booking – receipt page (prefer {@see Booking::$receipt_token}). */
    public static function booking(string $receiptPublicId): string
    {
        return 'booking.' . $receiptPublicId;
    }

    /** Private channel for admin/staff dashboard updates. */
    public static function adminDashboard(): string
    {
        return 'admin.dashboard';
    }

    /** Public: blocked dates updated – calendar/booking form. */
    public static function blockedDates(): string
    {
        return 'blocked-dates';
    }

    /** Public: rooms list updated – Step1 & homepage. */
    public static function rooms(): string
    {
        return 'rooms';
    }

    /** Public: venues list updated – homepage. */
    public static function venues(): string
    {
        return 'venues';
    }

    /** Public: gallery updated – homepage. */
    public static function gallery(): string
    {
        return 'gallery';
    }

    /** Public: reviews/testimonials updated – landing page. */
    public static function reviews(): string
    {
        return 'reviews';
    }

    public static function bookingCancelled(string $reference): string 
    { 
        return "booking.{$reference}.cancelled"; 
    }
}
