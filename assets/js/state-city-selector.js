/**
 * MN Order Panel - State City Selector
 * کامپوننت انتخاب استان و شهر با کش
 */

class StateCitySelector {
    constructor(stateSelectId, citySelectId) {
        this.stateSelect = document.getElementById(stateSelectId);
        this.citySelect = document.getElementById(citySelectId);
        this.cache = {
            states: null,
            cities: {}
        };
        
        this.init();
    }
    
    /**
     * مقداردهی اولیه
     */
    init() {
        // بارگذاری استان‌ها
        this.loadStates();
        
        // Event listener برای تغییر استان
        this.stateSelect.addEventListener('change', () => {
            const stateId = this.stateSelect.value;
            if (stateId) {
                this.loadCities(stateId);
            } else {
                this.clearCities();
            }
        });
    }
    
    /**
     * بارگذاری لیست استان‌ها
     */
    async loadStates() {
        try {
            // چک کش محلی (localStorage)
            const localCache = this.getLocalCache('states');
            if (localCache) {
                this.renderStates(localCache);
                return;
            }
            
            // دریافت از سرور
            const response = await fetch('../ajax/get-states-cities.php?type=states');
            const result = await response.json();
            
            if (result.success) {
                this.cache.states = result.data;
                this.renderStates(result.data);
                
                // ذخیره در localStorage
                this.setLocalCache('states', result.data);
            } else {
                console.error('خطا در دریافت استان‌ها:', result.message);
            }
        } catch (error) {
            console.error('خطا در بارگذاری استان‌ها:', error);
        }
    }
    
    /**
     * بارگذاری شهرهای یک استان
     */
    async loadCities(stateId) {
        try {
            // نمایش لودینگ
            this.citySelect.innerHTML = '<option value="">در حال بارگذاری...</option>';
            this.citySelect.disabled = true;
            
            // چک کش محلی
            const cacheKey = `cities_${stateId}`;
            const localCache = this.getLocalCache(cacheKey);
            if (localCache) {
                this.renderCities(localCache);
                return;
            }
            
            // چک کش حافظه
            if (this.cache.cities[stateId]) {
                this.renderCities(this.cache.cities[stateId]);
                return;
            }
            
            // دریافت از سرور
            const response = await fetch(`../ajax/get-states-cities.php?type=cities&parent_id=${stateId}`);
            const result = await response.json();
            
            if (result.success) {
                this.cache.cities[stateId] = result.data;
                this.renderCities(result.data);
                
                // ذخیره در localStorage
                this.setLocalCache(cacheKey, result.data);
            } else {
                console.error('خطا در دریافت شهرها:', result.message);
                this.citySelect.innerHTML = '<option value="">خطا در بارگذاری</option>';
            }
        } catch (error) {
            console.error('خطا در بارگذاری شهرها:', error);
            this.citySelect.innerHTML = '<option value="">خطا در بارگذاری</option>';
        } finally {
            this.citySelect.disabled = false;
        }
    }
    
    /**
     * رندر کردن استان‌ها
     */
    renderStates(states) {
        this.stateSelect.innerHTML = '<option value="">انتخاب استان</option>';
        
        states.forEach(state => {
            const option = document.createElement('option');
            option.value = state.id;
            option.textContent = state.name;
            option.dataset.slug = state.slug;
            option.dataset.citiesCount = state.cities_count;
            this.stateSelect.appendChild(option);
        });
    }
    
    /**
     * رندر کردن شهرها
     */
    renderCities(cities) {
        this.citySelect.innerHTML = '<option value="">انتخاب شهر</option>';
        
        if (cities.length === 0) {
            this.citySelect.innerHTML = '<option value="">شهری یافت نشد</option>';
            return;
        }
        
        cities.forEach(city => {
            const option = document.createElement('option');
            option.value = city.id;
            option.textContent = city.name;
            option.dataset.slug = city.slug;
            this.citySelect.appendChild(option);
        });
    }
    
    /**
     * پاک کردن لیست شهرها
     */
    clearCities() {
        this.citySelect.innerHTML = '<option value="">ابتدا استان را انتخاب کنید</option>';
        this.citySelect.disabled = true;
    }
    
    /**
     * دریافت از کش محلی
     */
    getLocalCache(key) {
        try {
            const cached = localStorage.getItem(`state_city_${key}`);
            if (!cached) return null;
            
            const data = JSON.parse(cached);
            const age = Date.now() - data.timestamp;
            
            // کش 1 ساعته
            if (age > 3600000) {
                localStorage.removeItem(`state_city_${key}`);
                return null;
            }
            
            return data.value;
        } catch (error) {
            return null;
        }
    }
    
    /**
     * ذخیره در کش محلی
     */
    setLocalCache(key, value) {
        try {
            const data = {
                value: value,
                timestamp: Date.now()
            };
            localStorage.setItem(`state_city_${key}`, JSON.stringify(data));
        } catch (error) {
            console.warn('خطا در ذخیره کش:', error);
        }
    }
    
    /**
     * پاک کردن کش
     */
    clearCache() {
        this.cache = { states: null, cities: {} };
        
        // پاک کردن localStorage
        Object.keys(localStorage).forEach(key => {
            if (key.startsWith('state_city_')) {
                localStorage.removeItem(key);
            }
        });
    }
    
    /**
     * دریافت استان انتخاب شده
     */
    getSelectedState() {
        const option = this.stateSelect.selectedOptions[0];
        if (!option || !option.value) return null;
        
        return {
            id: parseInt(option.value),
            name: option.textContent,
            slug: option.dataset.slug
        };
    }
    
    /**
     * دریافت شهر انتخاب شده
     */
    getSelectedCity() {
        const option = this.citySelect.selectedOptions[0];
        if (!option || !option.value) return null;
        
        return {
            id: parseInt(option.value),
            name: option.textContent,
            slug: option.dataset.slug
        };
    }
    
    /**
     * تنظیم مقدار استان
     */
    setStateValue(stateId) {
        this.stateSelect.value = stateId;
        if (stateId) {
            this.loadCities(stateId);
        }
    }
    
    /**
     * تنظیم مقدار شهر
     */
    setCityValue(cityId) {
        // باید کمی صبر کنیم تا شهرها لود بشن
        const checkInterval = setInterval(() => {
            if (this.citySelect.options.length > 1) {
                this.citySelect.value = cityId;
                clearInterval(checkInterval);
            }
        }, 100);
        
        // timeout بعد از 5 ثانیه
        setTimeout(() => clearInterval(checkInterval), 5000);
    }
}

// استفاده:
// const selector = new StateCitySelector('state-select', 'city-select');