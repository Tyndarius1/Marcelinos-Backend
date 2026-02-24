<div x-data="{ isDark: document.documentElement.classList.contains('dark') }"
     x-init="
        new MutationObserver(() => { isDark = document.documentElement.classList.contains('dark') })
            .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })
     "
     class="flex items-center">

    <img src="https://marcelinos-backend.test/brand-logo.webp" 
         alt="Marcelino's Logo" 
         class="h-12 w-auto object-contain">

    <div class="ml-2 leading-tight">
        <div 
          class="text-[19px] font-extrabold tracking-widest font-serif"
          :class="isDark ? 'text-[#09DF72]' : 'text-[#044835]'">
            MARCELINO'S
        </div>
        <div 
          class="text-sm font-medium tracking-widest"
          :class="isDark ? 'text-gray-300' : 'text-gray-900'">
            RESORT AND HOTEL
        </div>
    </div>

</div>