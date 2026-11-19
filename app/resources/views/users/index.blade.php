<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="kicker">Admin</p>
                <h2 class="mt-2 text-3xl font-semibold tracking-tight text-[#1A1D23]">Users</h2>
                <p class="mt-2 max-w-xl text-[15px] text-[#5C534C]">
                    Create a Glory SMM login per client. Then open their Chrome profile, sign in here, and Connect their social accounts.
                </p>
            </div>
            <a href="{{ route('users.create') }}" class="btn-primary shrink-0">New user</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="panel overflow-hidden">
                <table class="min-w-full divide-y divide-[#E4E7EC] text-sm">
                    <thead class="bg-[#F5F6F8]/60 text-left text-xs uppercase tracking-wider text-[#5C6570]">
                        <tr>
                            <th class="px-5 py-3 font-medium">Name</th>
                            <th class="px-5 py-3 font-medium">Email</th>
                            <th class="px-5 py-3 font-medium">Role</th>
                            <th class="px-5 py-3 font-medium text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E4E7EC]">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-5 py-3 font-medium text-[#1A1D23]">{{ $user->name }}</td>
                                <td class="px-5 py-3 text-[#5C6570]">{{ $user->email }}</td>
                                <td class="px-5 py-3">
                                    @if ($user->is_admin)
                                        <span class="rounded-full bg-glory-50 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-glory-600">Admin</span>
                                    @else
                                        <span class="rounded-full bg-[#EEF0F3] px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-[#5C6570]">Client</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-2">
                                        @unless ($user->is_admin)
                                            <a href="{{ route('users.edit', $user) }}" class="btn-secondary">Edit</a>
                                            <form method="POST" action="{{ route('users.destroy', $user) }}"
                                                  onsubmit="return confirm('Delete this user and all their connected accounts/posts?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-danger-ghost text-xs">Delete</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-[#8B939E]">—</span>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-8 text-center text-[#5C6570]">No users yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
