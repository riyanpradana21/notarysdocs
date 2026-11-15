<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can view any documents.
     */
    public function viewAny(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the document.
     */
    public function view(?User $user, Document $document): bool
    {
        if ($document->user_id !== null) {
            return $user !== null && $user->id === $document->user_id;
        }

        return $document->session_id !== null && $document->session_id === session()->getId();
    }

    /**
     * Determine whether the user can create documents.
     */
    public function create(?User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the document.
     */
    public function update(?User $user, Document $document): bool
    {
        if ($document->user_id !== null) {
            return $user !== null && $user->id === $document->user_id;
        }

        return $document->session_id !== null && $document->session_id === session()->getId();
    }

    /**
     * Determine whether the user can delete the document.
     */
    public function delete(?User $user, Document $document): bool
    {
        if ($document->user_id !== null) {
            return $user !== null && $user->id === $document->user_id;
        }

        return $document->session_id !== null && $document->session_id === session()->getId();
    }
}
