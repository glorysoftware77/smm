<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2.5 bg-red-600 border border-transparent rounded-lg font-semibold text-[15px] text-white hover:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-offset-2 focus:ring-offset-[#0B0F14] transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
