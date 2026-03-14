package com.gb.launcher

import android.os.Bundle
import android.widget.Button
import android.widget.EditText
import android.widget.ImageButton
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import com.gb.launcher.util.PrefsManager

class SettingsActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_settings)

        val etUrl    = findViewById<EditText>(R.id.et_api_url)
        val btnSave  = findViewById<Button>(R.id.btn_save_url)
        val btnBack  = findViewById<ImageButton>(R.id.btn_back)

        // Carrega URL atual
        etUrl.setText(PrefsManager.getApiUrl(this))

        btnBack.setOnClickListener { finish() }

        btnSave.setOnClickListener {
            val url = etUrl.text.toString().trim()
            if (url.isNotEmpty() && (url.startsWith("http://") || url.startsWith("https://"))) {
                PrefsManager.setApiUrl(this, url)
                Toast.makeText(this, "URL configurada com sucesso!", Toast.LENGTH_SHORT).show()
                finish()
            } else {
                Toast.makeText(this, "Insira uma URL válida (http/https)", Toast.LENGTH_SHORT).show()
            }
        }
    }
}
