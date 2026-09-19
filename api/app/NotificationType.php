<?php

namespace App;

enum NotificationType: string
{
    case ExpenseCreated = 'expense_created';
    case PaymentReceived = 'payment_received';
    case SettleUpReminders = 'settle_up_reminders';
}
