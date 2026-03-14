package com.gb.launcher

import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.net.Uri
import android.os.Bundle
import android.view.KeyEvent
import android.view.View
import android.widget.*
import androidx.activity.viewModels
import androidx.appcompat.app.AppCompatActivity
import androidx.recyclerview.widget.GridLayoutManager
import androidx.recyclerview.widget.RecyclerView
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout
import com.bumptech.glide.Glide
import com.gb.launcher.data.model.AppItem
import com.gb.launcher.data.model.LauncherSettings
import com.gb.launcher.ui.MainViewModel
import com.gb.launcher.ui.UiState
import com.gb.launcher.ui.adapter.AppTileAdapter
import com.gb.launcher.util.PrefsManager
import com.google.android.material.snackbar.Snackbar

class MainActivity : AppCompatActivity() {

    private val viewModel: MainViewModel by viewModels()
    private lateinit var adapter: AppTileAdapter

    // Views
    private lateinit var wallpaperImage: ImageView
    private lateinit var logoImage: ImageView
    private lateinit var titleText: TextView
    private lateinit var clockText: TextView
    private lateinit var dateText: TextView
    private lateinit var recyclerView: RecyclerView
    private lateinit var swipeRefresh: SwipeRefreshLayout
    private lateinit var loadingView: View
    private lateinit var errorView: View
    private lateinit var errorText: TextView
    private lateinit var btnSettings: ImageButton
    private lateinit var categoryChips: LinearLayout
    private lateinit var emptyView: View

    private var allApps: List<AppItem> = emptyList()
    private var currentCategory = "todos"

    // Atualização do relógio
    private val clockRunnable = object : Runnable {
        override fun run() {
            updateClock()
            clockText.postDelayed(this, 30_000)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        bindViews()
        setupRecyclerView()
        setupObservers()
        setupListeners()

        // Inicializa relógio
        updateClock()
        clockText.post(clockRunnable)

        // Carrega dados da API
        viewModel.loadData(this)
    }

    override fun onResume() {
        super.onResume()
        // Atualiza status de instalação ao voltar
        adapter.currentList.forEach { app ->
            app.isInstalled = isPackageInstalled(app.packageName)
        }
        adapter.notifyDataSetChanged()
    }

    override fun onDestroy() {
        super.onDestroy()
        clockText.removeCallbacks(clockRunnable)
    }

    // -------------------------------------------------------
    // Bind
    // -------------------------------------------------------
    private fun bindViews() {
        wallpaperImage = findViewById(R.id.iv_wallpaper)
        logoImage      = findViewById(R.id.iv_logo)
        titleText      = findViewById(R.id.tv_title)
        clockText      = findViewById(R.id.tv_clock)
        dateText       = findViewById(R.id.tv_date)
        recyclerView   = findViewById(R.id.rv_apps)
        swipeRefresh   = findViewById(R.id.swipe_refresh)
        loadingView    = findViewById(R.id.view_loading)
        errorView      = findViewById(R.id.view_error)
        errorText      = findViewById(R.id.tv_error_msg)
        btnSettings    = findViewById(R.id.btn_settings)
        categoryChips  = findViewById(R.id.ll_categories)
        emptyView      = findViewById(R.id.view_empty)
    }

    // -------------------------------------------------------
    // RecyclerView
    // -------------------------------------------------------
    private fun setupRecyclerView() {
        adapter = AppTileAdapter { app -> onAppTileClicked(app) }

        val spanCount = calculateSpanCount()
        recyclerView.apply {
            this.adapter = this@MainActivity.adapter
            layoutManager = GridLayoutManager(this@MainActivity, spanCount)
            setHasFixedSize(false)
            itemAnimator = null
        }
    }

    private fun calculateSpanCount(): Int {
        val dp = resources.displayMetrics.widthPixels / resources.displayMetrics.density
        return when {
            dp >= 1200 -> 6
            dp >= 960  -> 5
            dp >= 720  -> 4
            dp >= 480  -> 3
            else       -> 2
        }
    }

    // -------------------------------------------------------
    // Observers
    // -------------------------------------------------------
    private fun setupObservers() {
        viewModel.uiState.observe(this) { state ->
            swipeRefresh.isRefreshing = false
            when (state) {
                is UiState.Loading -> showLoading()
                is UiState.Success -> {
                    val data = state.data
                    allApps = data.apps ?: emptyList()
                    applySettings(data.settings)
                    buildCategoryChips(allApps)
                    filterAndSubmitApps("todos")
                    showContent()
                }
                is UiState.Error -> showError(state.message)
            }
        }
    }

    // -------------------------------------------------------
    // Listeners
    // -------------------------------------------------------
    private fun setupListeners() {
        swipeRefresh.setOnRefreshListener { viewModel.loadData(this) }
        btnSettings.setOnClickListener   { openSettings() }
        errorView.setOnClickListener     { viewModel.loadData(this) }
    }

    // -------------------------------------------------------
    // Estado da UI
    // -------------------------------------------------------
    private fun showLoading() {
        loadingView.visibility  = View.VISIBLE
        errorView.visibility    = View.GONE
        recyclerView.visibility = View.GONE
        emptyView.visibility    = View.GONE
    }

    private fun showContent() {
        loadingView.visibility  = View.GONE
        errorView.visibility    = View.GONE
        recyclerView.visibility = View.VISIBLE
    }

    private fun showError(msg: String) {
        loadingView.visibility  = View.GONE
        errorView.visibility    = View.VISIBLE
        recyclerView.visibility = View.GONE
        errorText.text = msg
    }

    // -------------------------------------------------------
    // Aparência
    // -------------------------------------------------------
    private fun applySettings(settings: LauncherSettings?) {
        settings ?: return

        // Wallpaper
        if (!settings.wallpaperUrl.isNullOrBlank()) {
            Glide.with(this)
                .load(settings.wallpaperUrl)
                .centerCrop()
                .into(wallpaperImage)
        }

        // Logo
        if (!settings.logoUrl.isNullOrBlank()) {
            Glide.with(this).load(settings.logoUrl).into(logoImage)
            logoImage.visibility = View.VISIBLE
        }

        // Título
        titleText.text = settings.launcherTitle ?: getString(R.string.app_name)

        // Cor primária
        try {
            settings.primaryColor?.let { hex ->
                val color = Color.parseColor(hex)
                swipeRefresh.setColorSchemeColors(color)
            }
        } catch (_: Exception) {}
    }

    // -------------------------------------------------------
    // Categorias
    // -------------------------------------------------------
    private fun buildCategoryChips(apps: List<AppItem>) {
        categoryChips.removeAllViews()

        val categories = listOf("todos") +
                apps.mapNotNull { it.category }.distinct().sorted()

        categories.forEachIndexed { index, slug ->
            val chip = layoutInflater.inflate(
                R.layout.item_category_chip, categoryChips, false
            ) as TextView
            chip.text = if (slug == "todos") "Todos" else slug.replaceFirstChar { it.uppercase() }
            chip.tag  = slug
            chip.isSelected = slug == currentCategory
            chip.setOnClickListener { filterAndSubmitApps(slug) }

            // Foco TV
            chip.setOnFocusChangeListener { _, hasFocus ->
                chip.animate().scaleX(if (hasFocus) 1.06f else 1f)
                    .scaleY(if (hasFocus) 1.06f else 1f).setDuration(100).start()
            }
            categoryChips.addView(chip)
        }
    }

    private fun filterAndSubmitApps(category: String) {
        currentCategory = category
        // Atualiza seleção visual
        for (i in 0 until categoryChips.childCount) {
            val chip = categoryChips.getChildAt(i) as? TextView
            chip?.isSelected = chip?.tag == category
        }

        val filtered = if (category == "todos") allApps
                       else allApps.filter { it.category == category }

        emptyView.visibility    = if (filtered.isEmpty()) View.VISIBLE else View.GONE
        recyclerView.visibility = if (filtered.isEmpty()) View.GONE    else View.VISIBLE
        adapter.submitList(filtered)
    }

    // -------------------------------------------------------
    // Lançar / Instalar App
    // -------------------------------------------------------
    private fun onAppTileClicked(app: AppItem) {
        if (app.isInstalled) {
            launchApp(app.packageName)
        } else {
            openPlayStore(app.packageName)
        }
    }

    private fun launchApp(packageName: String) {
        val intent = packageManager.getLaunchIntentForPackage(packageName)
        if (intent != null) {
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            startActivity(intent)
        } else {
            Snackbar.make(recyclerView, "App não encontrado no dispositivo.", Snackbar.LENGTH_SHORT).show()
        }
    }

    private fun openPlayStore(packageName: String) {
        try {
            startActivity(Intent(Intent.ACTION_VIEW,
                Uri.parse("market://details?id=$packageName")))
        } catch (e: Exception) {
            startActivity(Intent(Intent.ACTION_VIEW,
                Uri.parse("https://play.google.com/store/apps/details?id=$packageName")))
        }
    }

    private fun isPackageInstalled(packageName: String): Boolean = try {
        packageManager.getPackageInfo(packageName, 0)
        true
    } catch (e: PackageManager.NameNotFoundException) { false }

    // -------------------------------------------------------
    // Configurações
    // -------------------------------------------------------
    private fun openSettings() {
        startActivity(Intent(this, SettingsActivity::class.java))
    }

    // -------------------------------------------------------
    // Relógio
    // -------------------------------------------------------
    private fun updateClock() {
        val now = java.util.Calendar.getInstance()
        val hh  = String.format("%02d", now.get(java.util.Calendar.HOUR_OF_DAY))
        val mm  = String.format("%02d", now.get(java.util.Calendar.MINUTE))
        clockText.text = "$hh:$mm"

        val days   = arrayOf("Dom","Seg","Ter","Qua","Qui","Sex","Sáb")
        val months = arrayOf("Jan","Fev","Mar","Abr","Mai","Jun","Jul","Ago","Set","Out","Nov","Dez")
        val day    = days[now.get(java.util.Calendar.DAY_OF_WEEK) - 1]
        val d      = now.get(java.util.Calendar.DAY_OF_MONTH)
        val month  = months[now.get(java.util.Calendar.MONTH)]
        dateText.text = "$day, $d $month"
    }

    // -------------------------------------------------------
    // Back press → NOP (launcher não volta para nada)
    // -------------------------------------------------------
    @Deprecated("Deprecated in Java")
    override fun onBackPressed() { /* Launcher: não faz nada */ }

    override fun onKeyDown(keyCode: Int, event: KeyEvent?): Boolean {
        if (keyCode == KeyEvent.KEYCODE_HOME || keyCode == KeyEvent.KEYCODE_BACK) return true
        return super.onKeyDown(keyCode, event)
    }
}
