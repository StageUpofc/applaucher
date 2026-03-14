package com.gb.launcher.ui.adapter

import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.DiffUtil
import androidx.recyclerview.widget.ListAdapter
import androidx.recyclerview.widget.RecyclerView
import com.bumptech.glide.Glide
import com.bumptech.glide.load.engine.DiskCacheStrategy
import com.gb.launcher.R
import com.gb.launcher.data.model.AppItem

class AppTileAdapter(
    private val onAppClick: (AppItem) -> Unit
) : ListAdapter<AppItem, AppTileAdapter.TileViewHolder>(DiffCallback) {

    companion object DiffCallback : DiffUtil.ItemCallback<AppItem>() {
        override fun areItemsTheSame(old: AppItem, new: AppItem) = old.id == new.id
        override fun areContentsTheSame(old: AppItem, new: AppItem) = old == new
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): TileViewHolder {
        val v = LayoutInflater.from(parent.context).inflate(R.layout.item_app_tile, parent, false)
        return TileViewHolder(v)
    }

    override fun onBindViewHolder(holder: TileViewHolder, position: Int) {
        holder.bind(getItem(position))
    }

    inner class TileViewHolder(itemView: View) : RecyclerView.ViewHolder(itemView) {
        private val icon: ImageView    = itemView.findViewById(R.id.iv_icon)
        private val name: TextView     = itemView.findViewById(R.id.tv_name)
        private val pinBadge: View     = itemView.findViewById(R.id.view_pin_badge)

        fun bind(app: AppItem) {
            name.text = app.name
            pinBadge.visibility = if (app.isPinned) View.VISIBLE else View.GONE

            // Carrega ícone via URL (Glide) ou mostra letra inicial
            if (!app.iconUrl.isNullOrBlank()) {
                Glide.with(itemView.context)
                    .load(app.iconUrl)
                    .diskCacheStrategy(DiskCacheStrategy.ALL)
                    .placeholder(R.drawable.ic_app_placeholder)
                    .error(R.drawable.ic_app_placeholder)
                    .centerCrop()
                    .into(icon)
            } else {
                icon.setImageResource(R.drawable.ic_app_placeholder)
            }

            itemView.setOnClickListener {
                // Anima clique
                itemView.animate().scaleX(.92f).scaleY(.92f).setDuration(80).withEndAction {
                    itemView.animate().scaleX(1f).scaleY(1f).setDuration(80).start()
                }.start()
                onAppClick(app)
            }

            // Foco TV (control remoto)
            itemView.setOnFocusChangeListener { _, hasFocus ->
                itemView.animate()
                    .scaleX(if (hasFocus) 1.08f else 1f)
                    .scaleY(if (hasFocus) 1.08f else 1f)
                    .setDuration(120)
                    .start()
                itemView.elevation = if (hasFocus) 16f else 4f
            }
        }
    }
}
