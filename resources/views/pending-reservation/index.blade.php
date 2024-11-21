<x-admin-layout>
    <div>
        @if (session('success'))
            <x-flashMsg msg="{{ session('success') }}" />
        @elseif (session('deleted'))
            <x-flashMsg msg="{{ session('deleted') }}" bg="bg-red-500" />
        @elseif (session('error'))
            <x-flashMsg msg="{{ session('error') }}" bg="bg-red-500" />
        @endif
    </div>

    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
        <caption class="p-5 text-lg font-semibold text-left rtl:text-right text-gray-900 bg-white">
            Pending bookings
            <p class="mt-1 text-sm font-normal text-gray-500">Browse a list of pending bookings in our system.</p>

            <!-- Filter Form -->
            <form id="filter-form" class="flex flex-col md:flex-row mb-4">
                <input type="text" name="search" id="search-input" placeholder="Search by contact..."
                    class="p-2 border text-sm rounded w-full md:w-1/3 focus:ring-pink-600 mb-1 md:mr-2"
                    value="{{ request('search') }}" />
                <select name="month" class="p-2 border text-sm rounded w-full md:w-1/3 mb-1 md:mr-2">
                    <option value="">Filter by Month</option>
                    @for ($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" {{ request('month') == $i ? 'selected' : '' }}>
                            {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                        </option>
                    @endfor
                </select>
                <input type="number" name="day" min="1" max="31" placeholder="Day"
                    class="p-2 border text-sm rounded w-full md:w-1/3 mb-1 md:mr-2" value="{{ request('day') }}" />
                <button type="submit"
                    class="p-2 bg-blue-500 text-white rounded w-full md:w-auto text-sm hover:bg-blue-700">Filter</button>
            </form>
        </caption>

        <div class="hidden md:block">
            <table class="min-w-full text-sm text-left rtl:text-right text-gray-500">
                <thead class="text-xs text-gray-700 uppercase bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3">Name</th>
                        <th scope="col" class="px-6 py-3">Address</th>
                        <th scope="col" class="px-6 py-3">Pax</th>
                        <th scope="col" class="px-6 py-3">Contact</th>
                        <th scope="col" class="px-6 py-3">Check-in</th>
                        <th scope="col" class="px-6 py-3">Check-out</th>
                        <th scope="col" class="px-6 py-3">Total Amount</th>
                        <th scope="col" class="px-6 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody id="reservations-table-body">
                    @forelse ($reservations as $reservation)
                        <tr class="bg-white border-b hover:bg-gray-100"
                            onclick="window.location='{{ route('reservations.show', $reservation) }}'">
                            <td class="px-6 py-4">{{ $reservation->name }}</td>
                            <td class="px-6 py-4">{{ $reservation->address }}</td>
                            <td class="px-6 py-4">{{ $reservation->pax }}</td>
                            <td class="px-6 py-4">{{ $reservation->contact }}</td>
                            <td class="px-6 py-4">{{ $reservation->check_in->format('F j, Y h:i A') }}</td>
                            <td class="px-6 py-4">{{ $reservation->check_out->format('F j, Y h:i A') }}</td>
                            <td class="px-6 py-4">{{ number_format($reservation->total_amount, 2) }}</td>
                            <td class="px-6 py-4 flex items-center space-x-4">
                                <form action="{{ route('reservations.reserve', $reservation) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                        class="text-blue-500 hover:text-blue-700 font-semibold py-1 px-3 border border-blue-500 rounded-md transition-all duration-200 ease-in-out hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        Reserve
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-gray-500">No pending bookings found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <x-ConfirmationModal />
    <div class="p-4">
        {{ $reservations->links() }}
    </div>

    <script src="{{ asset('js/confirmation.js') }}"></script>
    <script src="{{ asset('js/search.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeSearch('filter-form', 'reservations-table-body', '{{ route('reservations.index') }}');
        });

        // Modal functions for confirm and cancel
        function openConfirmModal(reservationId, name) {
            // Code for opening confirmation modal
        }

        function openCancelModal(reservationId, name) {
            // Code for opening cancellation modal
        }
    </script>
</x-admin-layout>