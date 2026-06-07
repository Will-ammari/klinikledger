<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use App\Enums\InvoiceStatus;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->clinic_id === $invoice->clinic_id
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->clinic_id === $invoice->clinic_id
            && $invoice->canBeModified()
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

public function markPaid(User $user, Invoice $invoice): bool
{
    return $user->clinic_id === $invoice->clinic_id
        && $invoice->status === InvoiceStatus::Issued
        && in_array($user->role, [
            UserRole::OwnerClinic,
            UserRole::Receptionist,
        ], true);
}

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->clinic_id === $invoice->clinic_id
            && ! $invoice->isPaid()
            && ! $invoice->isCancelled()
            && in_array($user->role, [
                UserRole::OwnerClinic,
                UserRole::Receptionist,
            ], true);
    }
}
