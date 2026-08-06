<?php

namespace App\Livewire;

use App\Models\AccountMember;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

/**
 * The landing page for an emailed team invitation. Opening the tokenized
 * link proves email ownership, so a brand-new user created here is born
 * verified. Existing users authenticate (session or password) before the
 * membership is attached — the invite email alone never unlocks an account.
 */
class AcceptInvitePage extends Component
{
    public string $token = '';

    // New-user signup fields / existing-user password
    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $error = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    /** Re-resolve on every request — expiry/acceptance must always be fresh. */
    public function getInvitationProperty(): ?TeamInvitation
    {
        return TeamInvitation::findByToken($this->token)?->load(['account', 'role', 'inviter']);
    }

    /** The already-registered user this invite addresses, if any. */
    public function getExistingUserProperty(): ?User
    {
        $invitation = $this->invitation;

        return $invitation ? User::whereRaw('LOWER(email) = ?', [$invitation->email])->first() : null;
    }

    /** Logged-in user whose email matches — one click to join. */
    public function acceptAsCurrentUser(): void
    {
        $invitation = $this->invitation;
        $user = Auth::user();
        if (! $invitation || ! $user || mb_strtolower($user->email) !== $invitation->email) {
            $this->error = 'This invitation cannot be accepted from the current session.';

            return;
        }

        $this->finalize($invitation, $user);
    }

    /** Guest with an existing account — verify their password, then join. */
    public function acceptWithPassword(): void
    {
        $invitation = $this->invitation;
        $user = $this->existingUser;
        if (! $invitation || ! $user) {
            $this->error = 'This invitation is no longer valid.';

            return;
        }

        $this->validate(['password' => ['required', 'string']]);

        if (! Hash::check($this->password, $user->password)) {
            $this->error = 'That password is not correct.';

            return;
        }

        Auth::login($user);
        $this->finalize($invitation, $user);
    }

    /** Guest with no account — create one (verified by the token) and join. */
    public function acceptAndRegister(): void
    {
        $invitation = $this->invitation;
        if (! $invitation || $this->existingUser) {
            $this->error = 'This invitation is no longer valid.';

            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $invitation->email,
            'password' => Hash::make($this->password),
            'email_verified_at' => now(), // possession of the emailed token IS the verification
        ]);

        Auth::login($user);
        $this->finalize($invitation, $user);
    }

    private function finalize(TeamInvitation $invitation, User $user): void
    {
        AccountMember::updateOrCreate(
            ['account_id' => $invitation->account_id, 'user_id' => $user->id],
            ['role_id' => $invitation->role_id],
        );
        $invitation->update(['accepted_at' => now()]);

        session()->regenerate();
        $this->redirect(route('home'));
    }

    public function render()
    {
        return view('livewire.accept-invite-page', [
            'invitation' => $this->invitation,
            'existingUser' => $this->existingUser,
        ]);
    }
}
