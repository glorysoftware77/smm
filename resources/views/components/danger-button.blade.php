<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2.5 bg-[#C0352B] border border-transparent rounded-lg font-semibold text-[15px] text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-white transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
