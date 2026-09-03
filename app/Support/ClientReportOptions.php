<?php

namespace App\Support;

/**
 * Fixed option lists for the daily client engagement report, per the
 * boss's WhatsApp brief (2026-09-02/03). Type of Engagement is trimmed
 * from his checklist (dropped "No activity undertaken" - if nothing
 * happened there's nothing to report). Current Stage is kept close to his
 * suggested pipeline list since it's genuinely useful for tracking
 * progress over time, not one of the fields trimmed for brevity.
 */
class ClientReportOptions
{
    public const ENGAGEMENT_TYPES = [
        'Physical visit',
        'Phone call',
        'Email',
        'WhatsApp / Message',
        'Virtual meeting',
        'Follow-up',
    ];

    public const STAGES = [
        'Not yet contacted',
        'Initial contact attempted',
        'Initial contact successful',
        'Relevant contact person identified',
        'Awaiting response',
        'Meeting requested',
        'Meeting scheduled',
        'Meeting held',
        'Follow-up in progress',
        'Institution interested',
        'Proposal/information requested',
        'Not interested',
        'No current opportunity',
        'Opportunity completed/closed',
    ];
}
